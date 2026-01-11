<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoTercero extends Model
{
    use HasFactory;

    protected $table = 'tipo_tercero';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Terceros que tienen este tipo
     */
    public function terceros(): BelongsToMany
    {
        return $this->belongsToMany(Tercero::class, 'tercero_tipo');
    }

    /**
     * Scope para tipos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
