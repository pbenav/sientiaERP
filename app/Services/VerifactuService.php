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
     */
    public function enviarAEAT(Model $model): bool
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            return false;
        }

        // 1. Generar XML
        $xmlBuilder = app(VerifactuXmlService::class);
        $xml = $xmlBuilder->generateAltaXml($model);
        
        // 2. Enviar a la AEAT
        $aeat = app(AeatService::class);
        $res = $aeat->submitAlta($xml);
        
        if ($res['success']) {
            $model->update([
                'verifactu_status' => 'accepted',
                'verifactu_aeat_id' => $res['trace_id'] ?? 'OK'
            ]);
            return true;
        }
        
        $model->update([
            'verifactu_status' => 'error',
            'verifactu_signature' => substr($res['error'], 0, 1000) // Guardar error para depuración
        ]);
        
        return false;
    }

    /**
     * Generar la URL para el código QR de Veri*Factu (URL de la AEAT)
     */
    protected function generarQrUrl(Model $model, string $huella): string
    {
        $nif = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        
        $params = [
            'nif' => $nif,
            'num' => $model->numero,
            'fec' => $fecha,
            'imp' => number_format($model->total, 2, '.', ''),
        ];

        return "https://www2.agenciatributaria.gob.es/wlpl/ARET-LITX/VERIFACTU/Consulta?" . http_build_query($params);
    }
}
