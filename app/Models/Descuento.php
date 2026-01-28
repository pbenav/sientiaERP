<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Model;

class Descuento extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, \Illuminate\Database\Eloquent\SoftDeletes, HasUppercaseDisplay;

    protected $fillable = [
        'nombre',
        'valor',
        'es_predeterminado',
        'activo',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'es_predeterminado' => 'boolean',
        'activo' => 'boolean',
    ];
}
