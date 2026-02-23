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
        'name',
        'description',
        'price',
        'purchase_price',
        'profit',
        'profit_margin',
        'stock',
        'active',
        'barcode',
        'tax_rate',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Buscar producto por SKU o código de barras
     */
    public static function findByCode(string $searchCode): ?self
    {
        return static::where(function($query) use ($searchCode) {
            $query->where('sku', $searchCode)
                ->orWhere('barcode', $searchCode);
        })
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
        $method = Setting::get('profit_calculation_method', 'from_purchase');
        return static::calculateSalePriceFromMargin($purchasePrice, $margin, $method);
    }

    /**
     * Calcular margen a partir de precio de compra y precio de venta
     */
    public static function calculateMarginFromPrices(float $purchasePrice, float $salePrice, string $method): float
    {
        if ($purchasePrice <= 0) return 0;

        if ($method === 'from_sale') {
            if ($salePrice <= 0) return 0;
            return (1 - ($purchasePrice / $salePrice)) * 100;
        }

        // Default: from_purchase
        return (($salePrice - $purchasePrice) / $purchasePrice) * 100;
    }

    /**
     * Calcular precio de venta a partir de precio de compra y margen
     */
    public static function calculateSalePriceFromMargin(float $purchasePrice, float $margin, string $method): float
    {
        $precision = (int) Setting::get('intermediate_precision', 3);
        if ($method === 'from_sale') {
            if ($margin >= 100) $margin = 99.99; // Evitar división por cero o negativa
            return round($purchasePrice / (1 - ($margin / 100)), $precision);
        }

        // Default: from_purchase
        return round($purchasePrice * (1 + ($margin / 100)), $precision);
    }

    /**
     * Obtener el precio psicológico inmediatamente superior
     */
    public static function getSuggestedPsychologicalPrice(float $price): float
    {
        if ($price <= 0) return 0.90;

        $integerPart = floor($price);
        
        $candidates = [
            $integerPart + 0.90,
            $integerPart + 0.95,
            $integerPart + 0.99,
            $integerPart + 1 + 0.90,
            $integerPart + 1 + 0.95,
            $integerPart + 1 + 0.99,
        ];

        $superior = null;

        foreach ($candidates as $candidate) {
            if ($candidate > $price + 0.001) { // Pequeño margen para evitar flotantes
                if ($superior === null || $candidate < $superior) {
                    $superior = $candidate;
                }
            }
        }
        
        return $superior ?? ($integerPart + 1.90);
    }

    /**
     * Recalcular el margen de beneficio basado en los precios actuales y el método de cálculo preferido
     */
    public function recalculateProfitMargin(): void
    {
        $method = Setting::get('profit_calculation_method', 'from_purchase');
        $purchasePrice = (float) $this->purchase_price;
        $salePrice = (float) $this->price;

        $precision = (int) Setting::get('intermediate_precision', 3);

        if ($purchasePrice > 0) {
            $this->profit = round($salePrice - $purchasePrice, $precision);
            $this->profit_margin = round(static::calculateMarginFromPrices($purchasePrice, $salePrice, $method), 2);
            
            // También actualizar en metadata si existe la clave
            if (isset($this->metadata['commercial_margin'])) {
                $metadata = $this->metadata;
                $metadata['commercial_margin'] = $this->profit_margin;
                $this->metadata = $metadata;
            }
        }
    }

    /**
     * Añadir registro al histórico de compras en metadata
     */
    public function addPurchaseHistory(float $gross, float $discount, float $net, float $margin, ?string $docNumber = null): void
    {
        $metadata = $this->metadata ?? [];
        $history = $metadata['purchase_history'] ?? [];
        
        $entry = [
            'date' => now()->format('Y-m-d H:i:s'),
            'purchase_price_gross' => $gross,
            'discount' => $discount,
            'purchase_price_net' => $net,
            'commercial_margin' => $margin,
            'document_number' => $docNumber,
        ];
        
        // Añadir al inicio para tener lo más reciente primero
        array_unshift($history, $entry);
        
        $metadata['purchase_history'] = $history;
        
        // También actualizamos los campos "last" para conveniencia
        $metadata['purchase_price'] = $net;
        $metadata['purchase_price_gross'] = $gross;
        $metadata['last_discount'] = $discount;
        $metadata['commercial_margin'] = $margin;
        
        $this->metadata = $metadata;
    }
}
