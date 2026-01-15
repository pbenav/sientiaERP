<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingSerie extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'nombre',
        'iva_defecto_id',
        'irpf_defecto_id',
        'sujeta_irpf',
        'devenga_iva',
        'activo',
    ];

    protected $casts = [
        'sujeta_irpf' => 'boolean',
        'devenga_iva' => 'boolean',
        'activo' => 'boolean',
    ];

    public function ivaDefecto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'iva_defecto_id');
    }

    public function irpfDefecto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'irpf_defecto_id');
    }
}
