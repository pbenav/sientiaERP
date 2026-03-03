<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportDraft extends Model
{
    protected $fillable = [
        'expedicion_compra_id',
        'user_id',
        'status',
        'provider_name',
        'provider_nif',
        'document_number',
        'document_date',
        'matched_provider_id',
        'subtotal',
        'total_discount',
        'total_amount',
        'items',
        'documento_path',
        'raw_text',
        'confirmed_at',
        'documento_id',
    ];

    protected $casts = [
        'items'          => 'array',
        'document_date'  => 'date',
        'confirmed_at'   => 'datetime',
        'subtotal'       => 'decimal:4',
        'total_discount' => 'decimal:4',
        'total_amount'   => 'decimal:4',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────────

    public function expedicionCompra(): BelongsTo
    {
        return $this->belongsTo(ExpedicionCompra::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'matched_provider_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Verifica si el documento asociado existe.
     * Si no existe pero hay un ID asignado, resetea el borrador.
     */
    public function verifyDocumentExistence(): bool
    {
        if ($this->documento_id && !$this->documento()->exists()) {
            \Illuminate\Support\Facades\DB::transaction(function() {
                // Resetear expedición asociada
                if ($this->expedicion_compra_id) {
                    $this->expedicionCompra()->update([
                        'recogido' => false,
                        'documento_id' => null
                    ]);
                }

                // Resetear este borrador
                $this->update([
                    'status' => 'pending',
                    'documento_id' => null,
                    'confirmed_at' => null
                ]);
            });

            return false;
        }

        return true;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'Pendiente',
            'confirmed' => 'Confirmado',
            'rejected'  => 'Rechazado',
            default     => $this->status,
        };
    }

    /**
     * Returns items enriched with current product data (for comparison in the UI).
     */
    public function getItemsWithProductDataAttribute(): array
    {
        $items = $this->items ?? [];

        foreach ($items as &$item) {
            if (!empty($item['matched_product_id'])) {
                $product = Product::find($item['matched_product_id']);
                if ($product) {
                    $item['existing_product'] = [
                        'id'             => $product->id,
                        'name'           => $product->name,
                        'sku'            => $product->sku,
                        'purchase_price' => $product->purchase_price,
                        'price'          => $product->price,
                        'stock'          => $product->stock,
                    ];
                }
            }
        }
        unset($item);

        return $items;
    }
}
