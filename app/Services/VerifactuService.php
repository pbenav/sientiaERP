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
        
        // 2. Generar el contenido canónico para el hash actual
        $contenido = $this->generarContenidoCanonico($model, $hashAnterior);
        
        // 3. Calcular SHA-256
        $huella = hash('sha256', $contenido);
        
        // 4. Actualizar el modelo
        $model->update([
            'verifactu_huella' => $huella,
            'verifactu_huella_anterior' => $hashAnterior,
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
     * Generar cadena de datos para el hash (Simplificado para fase 1)
     */
    protected function generarContenidoCanonico(Model $model, ?string $hashAnterior): string
    {
        $fecha = $model->fecha ? $model->fecha->format('Y-m-d') : ($model->completed_at ? $model->completed_at->format('Y-m-d') : now()->format('Y-m-d'));
        
        $partes = [
            $model->numero,
            $fecha,
            number_format($model->total, 2, '.', ''),
            $hashAnterior ?? str_repeat('0', 64)
        ];
        
        return implode('|', $partes);
    }

    /**
     * Enviar el registro a la AEAT.
     * Devuelve ['success' => bool, 'error' => string|null]
     */
    public function enviarAEAT(Model $model): array
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            return ['success' => false, 'error' => 'Servicio Veri*Factu no activo en ajustes.'];
        }

        // --- SEGURIDAD: NO REENVIAR SI YA ESTÁ ACEPTADO ---
        if ($model->verifactu_status === 'Aceptado') {
            return ['success' => true, 'error' => 'Este documento ya ha sido aceptado por la AEAT previamente.'];
        }

        // --- PREVENCIÓN DE DUPLICIDAD (CANJE DE TICKETS) ---
        if ($model instanceof Documento && $model->tipo === 'factura') {
            $ticket = Ticket::where('documento_id', $model->id)->first();
            if ($ticket && $ticket->verifactu_status === 'Aceptado') {
                $msg = "Factura de canje: Ya reportada como Ticket {$ticket->numero}.";
                Log::info("Verifactu: " . $msg);
                
                $model->update([
                    'verifactu_status' => 'Aceptado',
                    'verifactu_aeat_id' => 'REEMPLAZA:' . $ticket->numero,
                    'verifactu_qr_url' => $ticket->verifactu_qr_url,
                ]);

                return ['success' => true, 'error' => $msg];
            }
        }

        // 1. Asegurar Huella y Encadenamiento antes de generar XML
        $this->procesarEncadenamiento($model);

        // 2. Generar XML
        try {
            $xmlBuilder = app(VerifactuXmlService::class);
            $xml = $xmlBuilder->generateAltaXml($model);
            
            // 3. Enviar a la AEAT
            $aeat = app(AeatService::class);
            $res = $aeat->submitAlta($xml);
            
            if ($res['success']) {
                $model->update([
                    'verifactu_status' => 'Aceptado',
                    'verifactu_aeat_id' => $res['trace_id'] ?? 'OK'
                ]);
                return ['success' => true, 'error' => null];
            }
            
            $errorMsg = $res['error'] ?? 'Error desconocido en la comunicación con AEAT';
            $model->update([
                'verifactu_status' => 'error',
                'verifactu_signature' => substr($errorMsg, 0, 1000)
            ]);
            
            return ['success' => false, 'error' => $errorMsg];

        } catch (\Exception $e) {
            $errorMsg = "Error interno: " . $e->getMessage();
            Log::error("Verifactu Service: " . $errorMsg);
            $model->update([
                'verifactu_status' => 'error',
                'verifactu_signature' => substr($errorMsg, 0, 1000)
            ]);
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    protected function generarQrUrl(Model $model, string $huella): string
    {
        $mode = \App\Models\Setting::get('verifactu_mode', config('verifactu.mode', 'test'));
        $isProduction = ($mode === 'production');

        // URL Oficial de consulta de Veri*Factu (Tike)
        // En PRE la v1/f no está habilitada públicamente aún
        $baseUrl = $isProduction 
            ? \App\Models\Setting::get('verifactu_qr_url_production', "https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/v1/f")
            : \App\Models\Setting::get('verifactu_qr_url_test', "https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR");

        $nif = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        
        if ($isProduction) {
            $params = [
                'nif' => $nif,
                'num' => $model->numero,
                'fec' => $fecha,
                'imp' => number_format($model->total, 2, '.', ''),
            ];
        } else {
            // Parámetros para ValidarQR en PRE
            $params = [
                'nif' => $nif,
                'numserie' => $model->numero,
                'fecha' => $fecha,
                'importe' => number_format($model->total, 2, '.', ''),
            ];
        }

        return $baseUrl . "?" . http_build_query($params);
    }
}
