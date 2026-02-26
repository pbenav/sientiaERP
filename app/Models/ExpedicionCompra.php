<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ExpedicionCompra extends Model
{
    protected $table = 'expediciones_compra';

    protected $fillable = [
        'fecha',
        'proveedor',
        'direccion',
        'importe',
        'observaciones',
        'pagado',
        'recogido',
        'documento_path',
        'archivado',
    ];

    protected $casts = [
        'fecha'     => 'date',
        'importe'   => 'decimal:2',
        'pagado'    => 'boolean',
        'recogido'  => 'boolean',
        'archivado' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Solo expediciones del periodo activo (no archivadas). */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('archivado', false);
    }

    /** Pagadas pero aún no recogidas → alerta ⚠️ */
    public function scopePendientesRecogida(Builder $query): Builder
    {
        return $query->where('archivado', false)
                     ->where('pagado', true)
                     ->where('recogido', false);
    }

    /** Sin pagar todavía. */
    public function scopeSinPagar(Builder $query): Builder
    {
        return $query->where('archivado', false)->where('pagado', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** ¿Tiene alerta? (pagado pero no recogido) */
    public function tieneAlerta(): bool
    {
        return $this->pagado && !$this->recogido;
    }
}
