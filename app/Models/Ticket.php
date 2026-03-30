<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Traits\HasUppercaseDisplay;

class Ticket extends Model
{
    use HasFactory, HasUppercaseDisplay;

    protected $fillable = [
        'user_id',
        'tercero_id',
        'documento_id',
        'session_id', // UUID
        'tpv_slot',   // TPV Slot ID (1-4)
        'numero',     // Ticket number
        'status',
        'descuento_porcentaje',
        'descuento_importe',
        'subtotal',
        'tax',
        'total',
        'payment_method',
        'pago_efectivo',
        'pago_tarjeta',
        'amount_paid',
        'change_given',
        'completed_at',
        'cash_session_id',
        'verifactu_huella', 'verifactu_huella_anterior', 'verifactu_status',
        'verifactu_fecha_hora_huso', 'verifactu_tipo_huella',
        'verifactu_aeat_id', 'verifactu_qr_url', 'verifactu_signature',
    ];

    /**
     * Obtener el último ticket aceptado por la AEAT para encadenamiento.
     */
    public function getUltimaAceptada(): ?self
    {
        return self::where('status', 'completed')
            ->where('verifactu_status', 'Aceptado')
            ->whereNotNull('verifactu_huella')
            ->where('id', '!=', $this->id)
            ->orderBy('numero', 'desc')
            ->first();
    }

    protected $attributes = [
        'descuento_porcentaje' => 0,
        'descuento_importe' => 0,
        'pago_efectivo' => 0,
        'pago_tarjeta' => 0,
        'amount_paid' => 0,
        'change_given' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'total' => 0,
    ];

    protected $casts = [
        'descuento_porcentaje' => 'decimal:2',
        'descuento_importe' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'pago_efectivo' => 'decimal:2',
        'pago_tarjeta' => 'decimal:2',
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

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function cash_session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * Recalcular totales del ticket
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('subtotal') ?: 0;
        $this->tax = $this->items()->sum('tax_amount') ?: 0;
        
        $totalBruto = (float)$this->subtotal + (float)$this->tax;
        
        // Aplicar descuentos generales
        $perc = (float)($this->descuento_porcentaje ?? 0);
        $imp = (float)($this->descuento_importe ?? 0);
        
        $descuentoPorcentajeV = ($totalBruto * ($perc / 100));
        $this->total = round($totalBruto - $descuentoPorcentajeV - $imp, 2);

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
            if ($item->product) {
                $item->product->decrement('stock', $item->quantity);
            }
        }
    }

    /**
     * Anular el ticket y devolver stock
     */
    public function cancel(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        // Devolver stock de productos
        foreach ($this->items as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }

        $this->update([
            'status' => 'cancelled',
        ]);

        // VERIFACTU: Encolar anulación ante la AEAT
        if (\App\Models\Setting::get('verifactu_active', false)) {
            app(\App\Services\VerifactuService::class)->encolarAnulacion($this);
        }
    }

    /**
     * Scope para tickets abiertos
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Verificar si el ticket ya tiene factura generada
     */
    public function hasInvoice(): bool
    {
        return $this->documento_id !== null;
    }
    /**
     * Sanitizar campos que pueden contener basura binaria de respuestas de red (AEAT)
     */
    public function getVerifactuSignatureAttribute($value)
    {
        return $value ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
    }

    public function getVerifactuAeatIdAttribute($value)
    {
        return $value ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
    }
}
