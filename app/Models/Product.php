<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'stock',
        'active',
        'barcode',
        'tax_rate',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Buscar producto por SKU o código de barras
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('sku', $code)
            ->orWhere('barcode', $code)
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
}
