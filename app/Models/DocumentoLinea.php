<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoLinea extends Model
{
    use HasFactory;

    protected $fillable = [
        'documento_id', 'product_id',
        'orden', 'codigo', 'descripcion',
        'cantidad', 'unidad', 'precio_unitario', 'descuento',
        'subtotal', 'iva', 'importe_iva', 'irpf', 'importe_irpf', 'total'
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'importe_iva' => 'decimal:2',
        'irpf' => 'decimal:2',
        'importe_irpf' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($linea) {
            $linea->calcularImportes();
        });

        static::updating(function ($linea) {
            $linea->calcularImportes();
        });

        static::saved(function ($linea) {
            // Recalcular totales del documento padre
            $linea->documento->recalcularTotales();
        });

        static::deleted(function ($linea) {
            // Recalcular totales del documento padre
            $linea->documento->recalcularTotales();
        });
    }

    /**
     * Documento al que pertenece
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Producto asociado
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcular importes automáticamente
     */
    protected function calcularImportes(): void
    {
        // Subtotal = cantidad * precio_unitario
        $this->subtotal = $this->cantidad * $this->precio_unitario;

        // Aplicar descuento
        if ($this->descuento > 0) {
            $this->subtotal = $this->subtotal * (1 - ($this->descuento / 100));
        }

        // Calcular IVA
        $this->importe_iva = $this->subtotal * ($this->iva / 100);

        // El IRPF ahora es global al documento, lo ponemos a 0 en la línea
        $this->irpf = 0;
        $this->importe_irpf = 0;

        // Total línea = subtotal + IVA
        $this->total = $this->subtotal + $this->importe_iva;
    }

    /**
     * Cargar datos desde un producto
     */
    public function cargarDesdeProducto(Product $product, float $cantidad = 1): void
    {
        $this->product_id = $product->id;
        $this->codigo = $product->sku;
        $this->descripcion = $product->name;
        $this->cantidad = $cantidad;
        $this->precio_unitario = $product->price;
        $this->iva = $product->tax_rate;
        $this->unidad = 'Ud';
    }
}
