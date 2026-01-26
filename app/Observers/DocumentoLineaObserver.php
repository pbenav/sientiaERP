<?php

namespace App\Observers;

use App\Models\DocumentoLinea;
use App\Models\ProductPurchaseHistory;

class DocumentoLineaObserver
{
    /**
     * Handle the DocumentoLinea "created" event.
     */
    public function created(DocumentoLinea $linea): void
    {
        // Only track purchase documents (albaranes de compra, facturas de compra)
        $documento = $linea->documento;
        
        if (!$documento) {
            return;
        }

        $isPurchaseDocument = in_array($documento->tipo, [
            'albaran_compra',
            'factura_compra',
            'pedido_compra'
        ]);

        if (!$isPurchaseDocument || !$linea->product_id) {
            return;
        }

        // Create purchase history record
        ProductPurchaseHistory::create([
            'product_id' => $linea->product_id,
            'documento_id' => $documento->id,
            'documento_linea_id' => $linea->id,
            'supplier_id' => $documento->tercero_id,
            'purchase_price' => $linea->precio_unitario ?? 0,
            'quantity' => $linea->cantidad ?? 0,
            'total_amount' => $linea->total ?? 0,
            'currency' => 'EUR',
            'purchase_date' => $documento->fecha ?? now(),
            'document_number' => $documento->numero ?? $documento->referencia_proveedor,
            'supplier_name' => $documento->tercero?->nombre_comercial ?? $documento->tercero?->razon_social,
            'notes' => $linea->descripcion,
        ]);
    }

    /**
     * Handle the DocumentoLinea "updated" event.
     */
    public function updated(DocumentoLinea $linea): void
    {
        // Update the corresponding history record if it exists
        $history = ProductPurchaseHistory::where('documento_linea_id', $linea->id)->first();
        
        if ($history) {
            $history->update([
                'purchase_price' => $linea->precio_unitario ?? 0,
                'quantity' => $linea->cantidad ?? 0,
                'total_amount' => $linea->total ?? 0,
                'notes' => $linea->descripcion,
            ]);
        }
    }

    /**
     * Handle the DocumentoLinea "deleted" event.
     */
    public function deleted(DocumentoLinea $linea): void
    {
        // Optionally keep history even if line is deleted, or delete it
        // For now, we'll keep it (set null is handled by migration)
    }
}
