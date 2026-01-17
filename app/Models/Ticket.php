<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tercero_id',
        'session_id', // UUID
        'tpv_slot',   // TPV Slot ID (1-4)
        'status',
        'subtotal',
        'tax',
        'total',
        'payment_method',
        'amount_paid',
        'change_given',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_given' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->session_id)) {
                $ticket->session_id = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    /**
     * Recalcular totales del ticket
     */
    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum('subtotal');
        $this->tax = $items->sum('tax_amount');
        $this->total = $items->sum('total');

        $this->save();
    }

    /**
     * Completar el ticket
     */
    public function complete(string $paymentMethod, float $amountPaid): void
    {
        $this->update([
            'status' => 'completed',
            'payment_method' => $paymentMethod,
            'amount_paid' => $amountPaid,
            'change_given' => max(0, $amountPaid - $this->total),
            'completed_at' => now(),
        ]);

        // Decrementar stock de productos
        foreach ($this->items as $item) {
            $item->product->decrementStock($item->quantity);
        }
    }

    /**
     * Scope para tickets abiertos
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
