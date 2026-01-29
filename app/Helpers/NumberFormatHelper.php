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
        $format = Setting::get('decimal_separator', 'comma');
        return $format === 'comma' ? ',' : '.';
    }

    /**
     * Obtener el separador de miles configurado
     */
    public static function getThousandsSeparator(): string
    {
        $format = Setting::get('thousands_separator', 'dot');
        
        return match($format) {
            'comma' => ',',
            'dot' => '.',
            'space' => ' ',
            'none' => '',
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
        if (empty($value)) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        $decimalSep = self::getDecimalSeparator();
        $thousandsSep = self::getThousandsSeparator();

        // Si tenemos un punto y el ERP usa comas para decimales, pero NO hay ninguna coma en el texto,
        // asumimos que el punto es el separador decimal (entrada del usuario o float directo).
        if ($decimalSep === ',' && str_contains($value, '.') && !str_contains($value, ',')) {
            return (float) $value;
        }

        // Remover separadores de miles
        if ($thousandsSep !== '') {
            $value = str_replace($thousandsSep, '', $value);
        }

        // Convertir separador decimal al punto
        if ($decimalSep !== '.') {
            $value = str_replace($decimalSep, '.', $value);
        }

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
        $formatted = self::formatNumber($value, 2);
        $symbol = Setting::get('currency_symbol', '€');
        $position = Setting::get('currency_position', 'suffix');

        return $position === 'suffix' 
            ? "{$formatted} {$symbol}" 
            : "{$symbol} {$formatted}";
    }
}
