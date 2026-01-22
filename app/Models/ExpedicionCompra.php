<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpedicionCompra extends Model
{
    protected $table = 'expediciones_compra';

    protected $fillable = [
        'expedicion_id',
        'tercero_id',
        'fecha',
        'proveedor',    // texto libre de respaldo (legado)
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

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function expedicion(): BelongsTo
    {
        return $this->belongsTo(Expedicion::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tercero::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('archivado', false);
    }

    public function scopePendientesRecogida(Builder $query): Builder
    {
        return $query->where('pagado', true)->where('recogido', false);
    }

    public function scopeSinPagar(Builder $query): Builder
    {
        return $query->where('pagado', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function tieneAlerta(): bool
    {
        return $this->pagado && !$this->recogido;
    }
}
