<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tipo', 'numero', 'serie', 'fecha', 'tercero_id', 'user_id',
        'documento_origen_id', 'estado',
        'subtotal', 'descuento', 'base_imponible', 'iva', 'irpf', 'recargo_equivalencia', 'total',
        'forma_pago', 'dias_pago',
        'observaciones', 'observaciones_internas',
        'fecha_validez', 'fecha_entrega', 'fecha_vencimiento'
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_validez' => 'date',
        'fecha_entrega' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'iva' => 'decimal:2',
        'irpf' => 'decimal:2',
        'recargo_equivalencia' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($documento) {
            if (empty($documento->numero)) {
                $documento->numero = NumeracionDocumento::generarNumero($documento->tipo, $documento->serie ?? 'A');
            }
            if (empty($documento->fecha)) {
                $documento->fecha = now();
            }
        });
    }

    /**
     * Tercero (cliente/proveedor)
     */
    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    /**
     * Usuario que creó el documento
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Líneas del documento
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(DocumentoLinea::class)->orderBy('orden');
    }

    /**
     * Documento del que se originó (conversión)
     */
    public function documentoOrigen(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_origen_id');
    }

    /**
     * Documentos derivados de este
     */
    public function documentosDerivados(): HasMany
    {
        return $this->hasMany(Documento::class, 'documento_origen_id');
    }

    /**
     * Recalcular totales del documento
     */
    public function recalcularTotales(): void
    {
        $lineas = $this->lineas;

        $this->subtotal = $lineas->sum('subtotal');
        $this->iva = $lineas->sum('importe_iva');
        $this->irpf = $lineas->sum('importe_irpf');
        $this->base_imponible = $this->subtotal - $this->descuento;
        $this->total = $this->base_imponible + $this->iva - $this->irpf + $this->recargo_equivalencia;

        $this->save();
    }

    /**
     * Confirmar documento (cambiar de borrador a confirmado)
     */
    public function confirmar(): void
    {
        if ($this->estado === 'borrador') {
            $this->update(['estado' => 'confirmado']);
        }
    }

    /**
     * Anular documento
     */
    public function anular(): void
    {
        $this->update(['estado' => 'anulado']);
    }

    /**
     * Convertir a otro tipo de documento
     */
    public function convertirA(string $tipoDestino): self
    {
        $nuevoDocumento = $this->replicate();
        $nuevoDocumento->tipo = $tipoDestino;
        $nuevoDocumento->numero = null; // Se generará automáticamente
        $nuevoDocumento->documento_origen_id = $this->id;
        $nuevoDocumento->estado = 'borrador';
        $nuevoDocumento->save();

        // Copiar líneas
        foreach ($this->lineas as $linea) {
            $nuevaLinea = $linea->replicate();
            $nuevaLinea->documento_id = $nuevoDocumento->id;
            $nuevaLinea->save();
        }

        $nuevoDocumento->recalcularTotales();

        return $nuevoDocumento;
    }

    /**
     * Scopes por tipo de documento
     */
    public function scopePresupuestos($query)
    {
        return $query->where('tipo', 'presupuesto');
    }

    public function scopePedidos($query)
    {
        return $query->where('tipo', 'pedido');
    }

    public function scopeAlbaranes($query)
    {
        return $query->where('tipo', 'albaran');
    }

    public function scopeFacturas($query)
    {
        return $query->where('tipo', 'factura');
    }

    public function scopeRecibos($query)
    {
        return $query->where('tipo', 'recibo');
    }

    /**
     * Scopes por estado
     */
    public function scopeBorradores($query)
    {
        return $query->where('estado', 'borrador');
    }

    public function scopeConfirmados($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }
}
