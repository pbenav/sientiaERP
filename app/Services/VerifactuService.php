<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class VerifactuService
{
    /**
     * Generar la huella (hash) de un documento y encadenarlo al anterior.
     */
    public function procesarEncadenamiento(Model $model): string
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            return '';
        }

        // 1. Obtener el hash del documento anterior en la misma serie
        $hashAnterior = $this->obtenerHashAnterior($model);
        
        // 2. Generar Timestamp ISO 8601 una única vez para consistencia
        $fechaHoraHuso = now()->toIso8601String();

        // 3. Generar el contenido canónico para el hash actual
        $contenido = $this->generarContenidoCanonico($model, $hashAnterior, $fechaHoraHuso);
        
        // 4. Calcular SHA-256 en Mayúsculas (Requerido)
        $huella = strtoupper(hash('sha256', $contenido));
        
        // 5. Actualizar el modelo con trazabilidad completa
        $model->update([
            'verifactu_huella' => $huella,
            'verifactu_huella_anterior' => $hashAnterior,
            'verifactu_fecha_hora_huso' => $fechaHoraHuso,
            'verifactu_tipo_huella' => '01', // SHA-256
            'verifactu_qr_url' => $this->generarQrUrl($model, $huella)
        ]);
        
        Log::info("Verifactu: Documento {$model->numero} encadenado. Huella: {$huella}");
        
        return $huella;
    }

    /**
     * Buscar el hash del último documento confirmado de la misma serie.
     */
    protected function obtenerHashAnterior(Model $model): ?string
    {
        $class = get_class($model);
        
        // Buscamos el último que tenga huella, independientemente de si fue aceptado o no (la cadena sigue)
        $query = $class::where('id', '!=', $model->id)
            ->whereNotNull('verifactu_huella')
            ->orderBy('id', 'desc');

        if ($model instanceof Documento) {
            $query->where('serie', $model->serie)->where('tipo', $model->tipo);
        }
        
        $anterior = $query->first();
        
        return $anterior?->verifactu_huella;
    }

    /**
     * Generar cadena de datos para el hash SEGÚN ESPECIFICACIONES AEAT (nombre=valor&...)
     */
    protected function generarContenidoCanonico(Model $model, ?string $hashAnterior, string $fechaHoraHuso): string
    {
        $nifEmisor = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $numeroLimpio = str_replace(' ', '', $model->numero);
        $fechaExp = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        
        $isAnulacion = ($model instanceof Documento && $model->estado === 'anulado') 
                    || ($model instanceof Ticket && $model->status === 'cancelled');

        if ($isAnulacion) {
            // Formato Anulación: IDEmisorFacturaAnulada=VAL&NumSerieFacturaAnulada=VAL&FechaExpedicionFacturaAnulada=VAL&Huella=VAL&FechaHoraHusoGenRegistro=VAL
            $partes = [
                "IDEmisorFacturaAnulada={$nifEmisor}",
                "NumSerieFacturaAnulada={$numeroLimpio}",
                "FechaExpedicionFacturaAnulada={$fechaExp}",
                "Huella=" . ($hashAnterior ?? ""),
                "FechaHoraHusoGenRegistro={$fechaHoraHuso}"
            ];
        } else {
            $tipoFactura = ($model instanceof Documento && $model->tipo === 'factura') ? 'F1' : 'F2';
            $importeTotal = number_format((float)$model->total, 2, '.', '');
            
            // Calcular CuotaTotal (Suma de todas las cuotas del desglose)
            $cuotaTotal = 0.0;
            $xmlBuilder = app(VerifactuXmlService::class);
            $desglose = $xmlBuilder->getDesgloseParaXml($model);
            foreach ($desglose as $linea) {
                $cuotaTotal += (float)$linea['cuota_iva'];
            }
            $cuotaTotalStr = number_format($cuotaTotal, 2, '.', '');

            // Formato Alta: IDEmisorFactura=VAL&NumSerieFactura=VAL&FechaExpedicionFactura=VAL&TipoFactura=VAL&CuotaTotal=VAL&ImporteTotal=VAL&Huella=VAL&FechaHoraHusoGenRegistro=VAL
            $partes = [
                "IDEmisorFactura={$nifEmisor}",
                "NumSerieFactura={$numeroLimpio}",
                "FechaExpedicionFactura={$fechaExp}",
                "TipoFactura={$tipoFactura}",
                "CuotaTotal={$cuotaTotalStr}",
                "ImporteTotal={$importeTotal}",
                "Huella=" . ($hashAnterior ?? ""),
                "FechaHoraHusoGenRegistro={$fechaHoraHuso}"
            ];
        }
        
        return implode('&', $partes);
    }

    /**
     * Encolar el envío asíncrono a VeriFactu.
     * Esto calcula la huella de encadenamiento antes de despachar el worker 
     * para prevenir condiciones de carrera en el orden cronológico.
     */
    public function encolar(Model $model): array
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            return ['success' => false, 'error' => 'Servicio inactivo'];
        }

        if ($model->verifactu_status === 'Aceptado') {
            return ['success' => true, 'error' => 'Ya fue aceptado previamente.'];
        }

        // --- VALIDAR REQUISITOS F1 ---
        if ($model instanceof Documento && $model->tipo === 'factura') {
            $nifDest = $model->tercero ? trim($model->tercero->nif_cif ?? '') : '';
            if (empty($nifDest)) {
                return ['success' => false, 'error' => 'Rechazado (Validación Interna): La AEAT requiere que el cliente tenga un NIF/CIF para emitir una Factura (F1).'];
            }
        }

        // 1. Asegurar Huella y Encadenamiento de manera síncrona
        // Solo calculamos y asignamos si aún no tiene huella
        if (empty($model->verifactu_huella)) {
            $this->procesarEncadenamiento($model);
        }

        $model->update(['verifactu_status' => 'pending']);

        \App\Jobs\EnviarVerifactuJob::dispatch($model);

        return ['success' => true, 'error' => null];
    }


    /**
     * Enviar el registro a la AEAT desde el Worker (Síncrono para el Job pero Asíncrono para el usuario).
     */
    public function enviarAEAT(Model $model): array
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            return ['success' => false, 'error' => 'Servicio Veri*Factu no activo en ajustes.'];
        }

        if ($model->verifactu_status === 'Aceptado') {
            return ['success' => true, 'error' => 'Este documento ya ha sido aceptado por la AEAT previamente.'];
        }

        try {
            $xmlBuilder = app(VerifactuXmlService::class);
            
            // Detectar si es una anulación (Documento: estado=anulado, Ticket: status=cancelled)
            $isAnulacion = ($model instanceof Documento && $model->estado === 'anulado') 
                        || ($model instanceof Ticket && $model->status === 'cancelled');

            if ($isAnulacion) {
                $xml = $xmlBuilder->generateAnulacionXml($model);
            } else {
                $xml = $xmlBuilder->generateAltaXml($model);
            }
            
            $aeat = app(AeatService::class);
            $res = $aeat->submitAlta($xml); 
            
            if ($res['success']) {
                $status = $isAnulacion ? 'Anulacion_Aceptada' : 'Aceptado';
                $model->update([
                    'verifactu_status' => $status,
                    'verifactu_aeat_id' => $res['trace_id'] ?? 'OK'
                ]);
                return ['success' => true, 'error' => null];
            }
            
            $errorMsg = $res['error'] ?? 'Error desconocido en la comunicación con AEAT';
            $model->update([
                'verifactu_status' => 'error',
                'verifactu_signature' => mb_convert_encoding(substr($errorMsg, 0, 1000), 'UTF-8', 'UTF-8')
            ]);
            
            return ['success' => false, 'error' => $errorMsg];

        } catch (\Exception $e) {
            $errorMsg = "Error interno: " . $e->getMessage();
            Log::error("Verifactu Service: " . $errorMsg);
            $model->update([
                'verifactu_status' => 'error',
                'verifactu_signature' => mb_convert_encoding(substr($errorMsg, 0, 1000), 'UTF-8', 'UTF-8')
            ]);
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Encolar anulación de factura.
     */
    public function encolarAnulacion(Model $model): void
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) return;

        // 1. Calcular huella de anulación (encadenada al último registro)
        $this->procesarEncadenamiento($model);

        // 2. Dispatch Job
        \App\Jobs\EnviarVerifactuJob::dispatch($model);
    }

    protected function generarQrUrl(Model $model, string $huella): string
    {
        $mode = \App\Models\Setting::get('verifactu_mode', config('verifactu.mode', 'test'));
        $isProduction = ($mode === 'production');

        $baseUrl = $isProduction 
            ? \App\Models\Setting::get('verifactu_qr_url_production', config('verifactu.qr_url.production'))
            : \App\Models\Setting::get('verifactu_qr_url_test', config('verifactu.qr_url.test'));

        $nif = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        
        // Parámetros estándar para Consulta Pública VERI*FACTU
        $params = $isProduction 
            ? ['nif' => $nif, 'num' => $model->numero, 'fec' => $fecha, 'imp' => number_format($model->total, 2, '.', '')]
            : ['nif' => $nif, 'numserie' => $model->numero, 'fecha' => $fecha, 'importe' => number_format($model->total, 2, '.', '')];

        return $baseUrl . "?" . http_build_query($params);
    }
}
