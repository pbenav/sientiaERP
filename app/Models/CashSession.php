<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'fondo_apertura',
        'efectivo_final_real',
        'total_tickets_efectivo',
        'total_tickets_tarjeta',
        'desfase',
        'notas',
        'desglose_efectivo',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'fondo_apertura' => 'decimal:2',
        'efectivo_final_real' => 'decimal:2',
        'total_tickets_efectivo' => 'decimal:2',
        'total_tickets_tarjeta' => 'decimal:2',
        'desfase' => 'decimal:2',
        'desglose_efectivo' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOpen(): bool
    {
        return $this->estado === 'open';
    }
}
