<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumeracionDocumento extends Model
{
    use HasFactory;

    protected $table = 'numeracion_documentos';

    protected $fillable = [
        'tipo',
        'serie',
        'anio',
        'ultimo_numero',
        'formato',
        'longitud_numero',
    ];

    protected $casts = [
        'anio' => 'integer',
        'ultimo_numero' => 'integer',
        'longitud_numero' => 'integer',
    ];

    /**
     * Generar siguiente número para un tipo y serie
     */
    public static function generarNumero(string $tipo, string $serie = 'A'): string
    {
        $anio = date('Y');

        $numeracion = static::firstOrCreate(
            [
                'tipo' => $tipo,
                'serie' => $serie,
                'anio' => $anio,
            ],
            [
                'ultimo_numero' => 0,
                'formato' => '{TIPO}-{ANIO}-{NUM}',
                'longitud_numero' => 4,
            ]
        );

        $numeracion->increment('ultimo_numero');
        $numero = str_pad($numeracion->ultimo_numero, $numeracion->longitud_numero, '0', STR_PAD_LEFT);

        // Generar número según formato
        $prefijo = strtoupper(substr($tipo, 0, 3));
        $numeroFinal = str_replace(
            ['{TIPO}', '{ANIO}', '{NUM}', '{SERIE}'],
            [$prefijo, $anio, $numero, $serie],
            $numeracion->formato
        );

        return $numeroFinal;
    }
}
