<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Impuesto extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'tipo',
        'valor',
        'activo',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'activo' => 'boolean',
    ];
}
