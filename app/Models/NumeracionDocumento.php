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
    public static function generarNumero(string $tipo, string $serie = null): string
    {
        $anio = date('Y');

        // Si no se especifica serie, buscamos la primera activa o usamos 'A' por defecto
        if (!$serie) {
            $serieRecord = BillingSerie::where('activo', true)->orderBy('codigo')->first();
            $serie = $serieRecord ? $serieRecord->codigo : 'A';
        }

        $numeracion = static::firstOrCreate(
            [
                'tipo' => $tipo,
                'serie' => $serie,
                'anio' => $anio,
            ],
            [
                'ultimo_numero' => 0,
                'formato' => '{TIPO}-{ANIO}-{SERIE}-{NUM}',
                'longitud_numero' => 4,
            ]
        );

        // Obtener prefijo según el tipo
        $prefijo = match($tipo) {
            'presupuesto' => 'PRE',
            'pedido' => 'PED',
            'albaran' => 'ALB',
            'factura' => 'FAC',
            'recibo' => 'REC',
            'pedido_compra' => 'PCO',
            'albaran_compra' => 'ACO',
            'factura_compra' => 'FCO',
            'recibo_compra' => 'RCO',
            default => strtoupper(substr($tipo, 0, 3)),
        };

        do {
            $numeracion->increment('ultimo_numero');
            $numeracion->refresh(); // Asegurar que tenemos el último dato

            $numero = str_pad($numeracion->ultimo_numero, $numeracion->longitud_numero, '0', STR_PAD_LEFT);

            $numeroFinal = str_replace(
                ['{TIPO}', '{ANIO}', '{NUM}', '{SERIE}'],
                [$prefijo, $anio, $numero, $serie],
                $numeracion->formato
            );
            
            // Si ya existe un documento con ese número (colisión), repetimos el bucle
            // que incrementará de nuevo el contador.
        } while (\App\Models\Documento::where('numero', $numeroFinal)->exists());

        return $numeroFinal;
    }
}
