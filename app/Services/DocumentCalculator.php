<?php

namespace App\Services;

class DocumentCalculator
{
    /**
     * Calculate tax breakdown from an array of lines.
     * 
     * @param array $lineas Array of line items (from form state or model relation)
     * @param bool $tieneRecargo Whether to apply Recargo de Equivalencia
     * @return array Breakdown structure
     */
    public static function calculate(array $lineas, bool $tieneRecargo = false): array
    {
        $breakdown = [];
        $totalBase = 0;
        $totalIva = 0;
        $totalRe = 0;
        $totalDocumento = 0;

        foreach ($lineas as $linea) {
            // Normalize input from form state (which might use keys like 'cantidad', 'precio_unitario', etc.)
            // Note: Repeater state keys match database columns usually
            
            $cantidad = floatval($linea['cantidad'] ?? 0);
            $precio = floatval($linea['precio_unitario'] ?? 0); // Can be 'precio_unitario' or 'precio' depending on context? In Manager it is 'precio_unitario'.
            $descuento = floatval($linea['descuento'] ?? 0);
            $ivaRate = floatval($linea['iva'] ?? 0);
            
            // Calculate base for this line
            $baseLinea = $cantidad * $precio;
            if ($descuento > 0) {
                $baseLinea = $baseLinea * (1 - ($descuento / 100));
            }
            // Round base per line as per standard invoicing? usually row total is rounded.
            // But for tax grouping, we sum bases? or sum quotas?
            // Spanish law: Can calculate line by line or by total base.
            // SientiaERP convention: seems to calculate per line in Manager (round(2)).
            // We'll trust the input subtotal if available, otherwise recalculate.
            
            if (isset($linea['subtotal'])) {
                 $baseLinea = floatval($linea['subtotal']);
            } else {
                 $baseLinea = round($baseLinea, 2);
            }

            // Key for grouping: Tax Rate
            $key = (string)$ivaRate;
            
            if (!isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'iva' => $ivaRate,
                    'base' => 0,
                    'cuota_iva' => 0,
                    're' => 0,
                    'cuota_re' => 0,
                    'total' => 0,
                ];
                
                // Determine RE rate if applicable
                if ($tieneRecargo) {
                    $breakdown[$key]['re'] = self::getRecargoRate($ivaRate);
                }
            }
            
            $breakdown[$key]['base'] += $baseLinea;
        }

        // Now calculate quotas on the summed bases (or sum line quotas? standard is sum bases -> calc quota)
        foreach ($breakdown as $key => &$data) {
            $data['cuota_iva'] = round($data['base'] * ($data['iva'] / 100), 2);
            
            if ($tieneRecargo) {
                $data['cuota_re'] = round($data['base'] * ($data['re'] / 100), 2);
            }
            
            $data['total'] = $data['base'] + $data['cuota_iva'] + $data['cuota_re'];
            
            $totalBase += $data['base'];
            $totalIva += $data['cuota_iva'];
            $totalRe += $data['cuota_re'];
            $totalDocumento += $data['total'];
        }

        return [
            'impuestos' => $breakdown, // Keyed by rate string
            'base_imponible' => $totalBase,
            'total_iva' => $totalIva,
            'total_re' => $totalRe,
            'total_documento' => $totalDocumento
        ];
    }

    protected static function getRecargoRate(float $iva): float
    {
        // Simple mapping based on standard Spanish rates
        // 21% -> 5.2%
        // 10% -> 1.4%
        // 4% -> 0.5%
        // 0% -> 0%
        // Ideally fetch from DB Impuesto model
        
        // Optimize: Fetch if possible. But for speed assuming standard or DB fetch.
        // Let's use a query to be safe, cached ideally.
        $impuesto = \App\Models\Impuesto::where('valor', $iva)->where('tipo', 'iva')->first();
        if ($impuesto && $impuesto->recargo) {
             return $impuesto->recargo;
        }
        
        // Fallback hardcoded
        if ($iva == 21) return 5.2;
        if ($iva == 10) return 1.4;
        if ($iva == 4) return 0.5;
        return 0;
    }
}
