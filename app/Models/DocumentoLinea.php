<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoLinea extends Model
{
    use HasFactory, HasUppercaseDisplay;

    protected $fillable = [
        'documento_id', 'product_id',
        'orden', 'codigo', 'descripcion',
        'cantidad', 'unidad', 'precio_unitario', 'descuento',
        'subtotal', 'iva', 'importe_iva', 'recargo_equivalencia', 'importe_recargo_equivalencia', 'irpf', 'importe_irpf', 'total'
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'importe_iva' => 'decimal:2',
        'recargo_equivalencia' => 'decimal:2',
        'importe_recargo_equivalencia' => 'decimal:2',
        'irpf' => 'decimal:2',
        'importe_irpf' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($linea) {
            // Auto-link or auto-create product for purchase documents
            // This runs for all save paths: manual edit, AI import, POS, etc.
            if (empty($linea->product_id)) {
                $documento = $linea->documento
                    ?? \App\Models\Documento::find($linea->documento_id);

                $esCompra = $documento && str_contains($documento->tipo, '_compra');

                if ($esCompra && !empty($linea->codigo)) {
                    // Try to find existing product (including inactive/soft-deleted)
                    $producto = Product::withTrashed()
                        ->where('sku', $linea->codigo)
                        ->orWhere('barcode', $linea->codigo)
                        ->first();

                    if ($producto) {
                        // Reactivate if needed
                        if ($producto->trashed()) $producto->restore();
                        if (!$producto->active) $producto->update(['active' => true]);
                        $linea->product_id = $producto->id;
                    } elseif (!empty($linea->descripcion)) {
                        // Product doesn't exist — create it from line data
                        $nuevoProd = Product::create([
                            'sku'            => $linea->codigo,
                            'barcode'        => $linea->codigo,
                            'name'           => $linea->descripcion,
                            'description'    => $linea->descripcion,
                            'purchase_price' => (float) ($linea->precio_unitario ?? 0),
                            'price'          => (float) ($linea->precio_unitario ?? 0),
                            'tax_rate'       => (float) ($linea->iva ?? 21),
                            'stock'          => 0,
                            'active'         => true,
                        ]);
                        $linea->product_id = $nuevoProd->id;
                    }
                }
            }

            $linea->calcularImportes();
        });


        static::updated(function ($linea) {
            // DIFERENCIAL DE STOCK: Solo si el documento ya había movido stock
            $documento = $linea->documento;
            if ($documento && $documento->stock_actualizado && str_contains($documento->tipo, '_compra')) {
                $nuevaCant = (float) $linea->cantidad;
                $viejaCant = (float) $linea->getOriginal('cantidad');
                
                $nuevoProdId = $linea->product_id;
                $viejoProdId = $linea->getOriginal('product_id');

                if ($nuevoProdId === $viejoProdId) {
                    // Mismo producto, solo cambia cantidad
                    $diff = $nuevaCant - $viejaCant;
                    if ($diff != 0 && $linea->product) {
                        $linea->product->increment('stock', $diff);
                    }
                } else {
                    // Cambio de producto: Restar vieja del viejo, sumar nueva al nuevo
                    if ($viejoProdId) {
                        $viejoProd = Product::find($viejoProdId);
                        if ($viejoProd) $viejoProd->decrement('stock', $viejaCant);
                    }
                    if ($nuevoProdId && $linea->product) {
                        $linea->product->increment('stock', $nuevaCant);
                    }
                }
            }
        });

        static::saved(function ($linea) {
            // Recalcular totales del documento padre
            $linea->documento->recalcularTotales();
        });

        static::deleted(function ($linea) {
            // REVERTIR STOCK al eliminar línea si el documento ya estaba confirmado
            $documento = $linea->documento;
            if ($documento && $documento->stock_actualizado && str_contains($documento->tipo, '_compra')) {
                if ($linea->product_id && $linea->product) {
                    $linea->product->decrement('stock', $linea->cantidad);
                }
            }

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
        $precisionLimit = (int) Setting::get('intermediate_precision', 3);
        // 1. Subtotal Línea = Cantidad * Precio Unitario
        $this->subtotal = round($this->cantidad * $this->precio_unitario, $precisionLimit);

        // 2. Aplicar Descuento a la Base
        if ($this->descuento > 0) {
            $this->subtotal = round($this->subtotal * (1 - ($this->descuento / 100)), $precisionLimit);
        }

        // 3. Obtener configuración de impuestos del documento/tercero
        $aplicaRE = false;
        if ($this->documento && $this->documento->tercero) {
            $aplicaRE = $this->documento->tercero->recargo_equivalencia;
            // Validar B2C: Si no es minorista en RE, no aplica
            // La lógica de negocio dice que solo aplica si el tercero tiene el check marcado
        }

        // 4. Calcular IVA (Cuota = Base * %IVA)
        $this->importe_iva = round($this->subtotal * ($this->iva / 100), $precisionLimit);

        // 5. Calcular Recargo de Equivalencia (Si aplica)
        $this->importe_recargo_equivalencia = 0;
        $this->recargo_equivalencia = 0;

        if ($aplicaRE) {
            // Mapa automático IVA -> RE (España)
            $mapaIvaRe = [
                '21.00' => 5.2,
                '10.00' => 1.4,
                '4.00' => 0.5,
            ];
            
            // Buscar correspondencia (convertir a string para evitar problemas de float)
            $ivaStr = number_format($this->iva, 2);
            if (isset($mapaIvaRe[$ivaStr])) {
                $this->recargo_equivalencia = $mapaIvaRe[$ivaStr];
            } else {
                // Fallback genérico o error? Por ahora 0 si no coincide con estándar
                // O podría intentar aproximar. Dejamos 0.
            }

            if ($this->recargo_equivalencia > 0) {
                $this->importe_recargo_equivalencia = round($this->subtotal * ($this->recargo_equivalencia / 100), $precisionLimit);
            }
        }

        // 6. IRPF (En línea siempre es 0, se calcula globalmente en el documento)
        $this->irpf = 0;
        $this->importe_irpf = 0;

        // 7. Total Línea = Subtotal + IVA + RE
        $this->total = round($this->subtotal + $this->importe_iva + $this->importe_recargo_equivalencia, $precisionLimit);
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
