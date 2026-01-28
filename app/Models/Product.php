<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUppercaseDisplay;

    protected $fillable = [
        'sku',
        'code',
        'name',
        'description',
        'price',
        'stock',
        'active',
        'barcode',
        'tax_rate',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Buscar producto por SKU, código o código de barras
     */
    public static function findByCode(string $searchCode): ?self
    {
        return static::where('sku', $searchCode)
            ->orWhere('code', $searchCode)
            ->orWhere('barcode', $searchCode)
            ->where('active', true)
            ->first();
    }

    /**
     * Verificar si hay stock disponible
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }

    /**
     * Decrementar stock
     */
    public function decrementStock(int $quantity): void
    {
        $this->decrement('stock', $quantity);
    }

    /**
     * Calcular precio con IVA
     */
    public function getPriceWithTaxAttribute(): float
    {
        return $this->price * (1 + ($this->tax_rate / 100));
    }

    /**
     * Obtener margen comercial de metadata
     */
    public function getMargin(): ?float
    {
        return $this->metadata['commercial_margin'] ?? null;
    }

    /**
     * Guardar margen comercial en metadata
     */
    public function setMargin(float $margin): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['commercial_margin'] = $margin;
        $this->metadata = $metadata;
    }

    /**
     * Guardar precio de compra en metadata
     */
    public function setPurchasePrice(float $purchasePrice): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['purchase_price'] = $purchasePrice;
        $this->metadata = $metadata;
    }

    /**
     * Obtener precio de compra de metadata
     */
    public function getPurchasePrice(): ?float
    {
        return $this->metadata['purchase_price'] ?? null;
    }

    /**
     * Calcular precio de venta (PVP) a partir de precio de compra y margen
     */
    public static function calculateRetailPrice(float $purchasePrice, float $margin): float
    {
        return round($purchasePrice * (1 + ($margin / 100)), 3);
    }
}
