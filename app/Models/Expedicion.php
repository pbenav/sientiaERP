<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedicion extends Model
{
    protected $table = 'expediciones';

    protected $fillable = [
        'nombre',
        'fecha',
        'lugar',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function compras(): HasMany
    {
        return $this->hasMany(ExpedicionCompra::class, 'expedicion_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function totalImporte(): float
    {
        return (float) $this->compras()->sum('importe');
    }

    public function pendientesRecogida(): int
    {
        return $this->compras()->where('pagado', true)->where('recogido', false)->count();
    }

    public function sinPagar(): int
    {
        return $this->compras()->where('pagado', false)->count();
    }
}
