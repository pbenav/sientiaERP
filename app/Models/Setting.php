<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label',
        'group',
    ];

    /**
     * Claves de configuración sensibles que deben guardarse cifradas en la base de datos.
     */
    protected static array $sensitiveKeys = [
        'verifactu_cert_password',
        'facturae_cert_password',
        'ai_gemini_api_key',
        'ai_openai_api_key',
        'google_application_credentials',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $value = $setting->value;

        // Desencriptar de forma transparente si es una clave sensible
        if (in_array($key, static::$sensitiveKeys) && !empty($value)) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Fallback automático si el dato antiguo no estaba encriptado aún
                return $value;
            }
        }

        return $value;
    }

    public static function set(string $key, $value, ?string $label = null, ?string $group = null)
    {
        // Global normalization for booleans
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        // Encriptar de forma transparente si es una clave sensible
        if (in_array($key, static::$sensitiveKeys) && !empty($value)) {
            $value = \Illuminate\Support\Facades\Crypt::encryptString($value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'label' => $label ?? (static::where('key', $key)->first()?->label),
                'group' => $group ?? (static::where('key', $key)->first()?->group ?? 'General'),
            ]
        );
    }
}
