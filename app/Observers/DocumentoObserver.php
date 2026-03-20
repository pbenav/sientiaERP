<?php

namespace App\Observers;

use App\Models\Documento;

class DocumentoObserver
{
    /**
     * Handle the Documento "updated" event.
     */
    public function updated(Documento $documento): void
    {
        // 1. Si es una factura y ha cambiado a estado 'confirmado' -> Generar recibos y Verifactu
        if (in_array($documento->tipo, ['factura', 'factura_compra']) && 
            $documento->isDirty('estado') && 
            $documento->estado === 'confirmado') {
            
            $this->intentarGenerarRecibos($documento);

            // Verifactu: solo si no tiene huella todavía
            if (empty($documento->verifactu_huella)) {
                try {
                    $service = app(\App\Services\VerifactuService::class);
                    $service->procesarEncadenamiento($documento);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Error Verifactu para Doc {$documento->numero}: " . $e->getMessage());
                }
            }
        }

        // 2. Si es un recibo y ha cambiado su estado de pago
        if (in_array($documento->tipo, ['recibo', 'recibo_compra']) && 
            $documento->isDirty('estado')) {
            
            $invoice = $documento->documentoOrigen;
            
            if ($invoice && in_array($invoice->tipo, ['factura', 'factura_compra'])) {
                $this->sincronizarEstadoFactura($invoice);
            }
        }
    }

    /**
     * Intentar generar recibos para una factura si no existen
     */
    protected function intentarGenerarRecibos(Documento $factura): void
    {
        try {
            $service = new \App\Services\RecibosService();
            $tipoRecibo = $factura->tipo === 'factura_compra' ? 'recibo_compra' : 'recibo';
            
            $tieneRecibos = Documento::where('documento_origen_id', $factura->id)
                ->where('tipo', $tipoRecibo)
                ->exists();

            if (!$tieneRecibos && $factura->forma_pago_id) {
                $service->generarRecibosDesdeFactura($factura);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generating receipts for invoice ' . $factura->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar el estado de la factura basado en sus recibos
     */
    protected function sincronizarEstadoFactura(Documento $factura): void
    {
        $tipoRecibo = $factura->tipo === 'factura_compra' ? 'recibo_compra' : 'recibo';
        
        // Buscar todos los recibos de esta factura
        $recibos = Documento::where('documento_origen_id', $factura->id)
            ->where('tipo', $tipoRecibo)
            ->get();
            
        if ($recibos->isEmpty()) {
            return;
        }
        
        // Verificar si todos están pagados
        $todosPagados = $recibos->every(fn($r) => $r->estado === 'pagado');
        
        if ($todosPagados) {
            if ($factura->estado !== 'pagado') {
                $factura->estado = 'pagado';
                $factura->save();
            }
        } else {
            // Si antes estaba pagada y ahora algún recibo no lo está, vuelve a 'confirmado'
            if ($factura->estado === 'pagado') {
                $factura->estado = 'confirmado';
                $factura->save();
            }
        }
    }
}
