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

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->first()?->value ?? $default;
    }

    public static function set(string $key, $value, ?string $label = null, ?string $group = null)
    {
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
