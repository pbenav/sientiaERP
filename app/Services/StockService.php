<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Actualizar stock desde un documento
     * 
     * @param Documento $documento
     * @param bool $reverse Si es true, revierte el movimiento (ej: al anular)
     * @return void
     */
    public function actualizarStockDesdeDocumento(Documento $documento, bool $reverse = false): void
    {
        // Solo los albaranes y facturas afectan al stock
        if (!in_array($documento->tipo, ['albaran', 'factura', 'albaran_compra', 'factura_compra'])) {
            return;
        }

        // Si ya está actualizado y no estamos revirtiendo, salir
        if ($documento->stock_actualizado && !$reverse) {
            return;
        }

        // Si NO está actualizado y estamos revirtiendo, salir (no hay nada que revertir)
        if (!$documento->stock_actualizado && $reverse) {
            return;
        }

        // REGLA DE ORO: Si es Factura y viene de Albarán, la Factura NO mueve stock 
        // (ya lo hizo el Albarán). 
        if (!$reverse && in_array($documento->tipo, ['factura', 'factura_compra'])) {
            $tipoOrigen = $documento->documentoOrigen?->tipo;
            if ($tipoOrigen && str_contains($tipoOrigen, 'albaran')) {
                // El albarán ya movió stock, salimos sin marcar este como actualizado
                // para que no intente revertir algo que no movió.
                return;
            }
        }

        DB::transaction(function () use ($documento, $reverse) {
            foreach ($documento->lineas as $linea) {
                if (!$linea->product_id) continue;

                $producto = Product::find($linea->product_id);
                if (!$producto) continue;

                $cantidad = $linea->cantidad;

                // Determinar dirección del movimiento
                // Ventas (albaran/factura): reducen stock (+)
                // Compras (albaran_compra/factura_compra): aumentan stock (-)
                // Si revertimos ($reverse), invertimos el signo.
                
                $esCompra = str_contains($documento->tipo, '_compra');
                
                if ($esCompra) {
                    // Compra: + stock
                    $movimiento = $reverse ? -$cantidad : $cantidad;
                } else {
                    // Venta: - stock
                    $movimiento = $reverse ? $cantidad : -$cantidad;
                }

                $producto->increment('stock', $movimiento);
            }

            $documento->update(['stock_actualizado' => !$reverse]);
        });
    }
}
