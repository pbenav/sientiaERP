<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasUppercaseDisplay;

class TicketItem extends Model
{
    use HasFactory, HasUppercaseDisplay;

    protected $fillable = [
        'ticket_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->calculateAmounts();
        });

        static::updating(function ($item) {
            $item->calculateAmounts();
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcular montos automáticamente
     */
    protected function calculateAmounts(): void
    {
        $grossSubtotal = $this->unit_price * $this->quantity;
        $this->subtotal = $grossSubtotal * (1 - ($this->discount_percentage / 100));
        $this->tax_amount = $this->subtotal * ($this->tax_rate / 100);
        $this->total = $this->subtotal + $this->tax_amount;
    }
}
