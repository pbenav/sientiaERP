<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchaseHistory extends Model
{
    protected $table = 'product_purchase_history';

    protected $fillable = [
        'product_id',
        'documento_id',
        'documento_linea_id',
        'supplier_id',
        'purchase_price',
        'quantity',
        'total_amount',
        'currency',
        'purchase_date',
        'document_number',
        'supplier_name',
        'notes',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function documentoLinea(): BelongsTo
    {
        return $this->belongsTo(DocumentoLinea::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'supplier_id');
    }

    /**
     * Get average purchase price for this product
     */
    public static function getAveragePriceForProduct(int $productId): ?float
    {
        return static::where('product_id', $productId)
            ->avg('purchase_price');
    }

    /**
     * Get last purchase price for this product
     */
    public static function getLastPriceForProduct(int $productId): ?float
    {
        return static::where('product_id', $productId)
            ->orderBy('purchase_date', 'desc')
            ->value('purchase_price');
    }

    /**
     * Get purchase history for a product from a specific supplier
     */
    public static function getProductSupplierHistory(int $productId, int $supplierId)
    {
        return static::where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->orderBy('purchase_date', 'desc')
            ->get();
    }
}
