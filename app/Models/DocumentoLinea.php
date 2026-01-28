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
        // 1. Subtotal Línea = Cantidad * Precio Unitario
        $this->subtotal = round($this->cantidad * $this->precio_unitario, 2);

        // 2. Aplicar Descuento a la Base
        if ($this->descuento > 0) {
            $this->subtotal = round($this->subtotal * (1 - ($this->descuento / 100)), 2);
        }

        // 3. Obtener configuración de impuestos del documento/tercero
        $aplicaRE = false;
        if ($this->documento && $this->documento->tercero) {
            $aplicaRE = $this->documento->tercero->recargo_equivalencia;
            // Validar B2C: Si no es minorista en RE, no aplica
            // La lógica de negocio dice que solo aplica si el tercero tiene el check marcado
        }

        // 4. Calcular IVA (Cuota = Base * %IVA)
        $this->importe_iva = round($this->subtotal * ($this->iva / 100), 2);

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
                $this->importe_recargo_equivalencia = round($this->subtotal * ($this->recargo_equivalencia / 100), 2);
            }
        }

        // 6. IRPF (En línea siempre es 0, se calcula globalmente en el documento)
        $this->irpf = 0;
        $this->importe_irpf = 0;

        // 7. Total Línea = Subtotal + IVA + RE
        // Nota: El IRPF se resta del total de la factura, no de la línea visualmente aquí, 
        // aunque matemáticamente: Total = Base + IVA + RE.
        $this->total = $this->subtotal + $this->importe_iva + $this->importe_recargo_equivalencia;
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
