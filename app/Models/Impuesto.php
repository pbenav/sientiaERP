<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Impuesto extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes, HasUppercaseDisplay;

    protected $fillable = [
        'nombre',
        'tipo',
        'valor',
        'activo',
        'es_predeterminado',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'activo' => 'boolean',
        'es_predeterminado' => 'boolean',
    ];
}
