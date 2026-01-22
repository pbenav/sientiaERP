<?php

namespace App\Helpers;

use App\Models\Setting;

class NumberFormatHelper
{
    /**
     * Obtener el separador decimal configurado
     */
    public static function getDecimalSeparator(): string
    {
        $format = Setting::get('decimal_separator', ',');
        
        // Handle both the character and the name
        if ($format === 'dot' || $format === '.') return '.';
        if ($format === 'comma' || $format === ',') return ',';
        
        return ','; // Default
    }

    /**
     * Obtener el separador de miles configurado
     */
    public static function getThousandsSeparator(): string
    {
        $format = Setting::get('thousands_separator', '.');
        
        return match($format) {
            'comma', ',' => ',',
            'dot', '.' => '.',
            'space', ' ' => ' ',
            'none', '' => '',
            default => '.',
        };
    }

    /**
     * Formatear un número según la configuración
     *
     * @param float|int|string $value
     * @param int $decimals
     * @return string
     */
    public static function formatNumber($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $numericValue = is_numeric($value) ? (float) $value : 0;
       
        return number_format(
            $numericValue,
            $decimals,
            self::getDecimalSeparator(),
            self::getThousandsSeparator()
        );
    }

    /**
     * Parsear un número formateado a float
     * Convierte del formato local al formato de PHP (punto decimal)
     *
     * @param string $value
     * @return float
     */
    public static function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (empty($value)) {
            return 0.0;
        }

        $value = (string) $value;
        $decimalSep = self::getDecimalSeparator();
        $thousandsSep = self::getThousandsSeparator();

        // 1. Clean up spaces
        $value = trim($value);

        // 2. Identify if the input uses a "standard" dot as decimal regardless of settings
        // (common when users paste or browser autofills)
        if ($decimalSep === ',' && str_contains($value, '.') && !str_contains($value, ',')) {
            // It has a dot but no comma, likely a standard float representation
            return (float) $value;
        }

        // 3. Remove thousands separator
        if ($thousandsSep !== '') {
            $value = str_replace($thousandsSep, '', $value);
        }

        // 4. Convert decimal separator to standard dot
        if ($decimalSep !== '.') {
            $value = str_replace($decimalSep, '.', $value);
        }

        // 5. Final cleanup of any non-numeric remains (except dot and minus)
        $value = preg_replace('/[^0-9.-]/', '', $value);

        return (float) $value;
    }

    /**
     * Formatear un valor monetario
     *
     * @param float|int|string $value
     * @return string
     */
    public static function formatCurrency($value): string
    {
        $precision = (int) Setting::get('final_precision', 2);
        $formatted = self::formatNumber($value, $precision);
        $symbol = Setting::get('currency_symbol', '€');
        $position = Setting::get('currency_position', 'suffix');

        return $position === 'suffix' 
            ? "{$formatted} {$symbol}" 
            : "{$symbol} {$formatted}";
    }
}
