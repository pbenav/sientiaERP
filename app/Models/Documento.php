<?php

namespace App\Models;

use App\Traits\BloqueoDocumentos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Documento extends Model
{
    use HasFactory, SoftDeletes, BloqueoDocumentos;

    protected $fillable = [
        'tipo', 'numero', 'serie', 'fecha', 'tercero_id', 'user_id',
        'documento_origen_id', 'estado',
        'subtotal', 'descuento', 'base_imponible', 'iva', 'irpf', 'recargo_equivalencia', 'total',
        'forma_pago_id',
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
     * Forma de pago
     */
    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class);
    }

    /**
     * Serie de facturación asociada
     */
    public function billingSerie(): BelongsTo
    {
        return $this->belongsTo(BillingSerie::class, 'serie', 'codigo');
    }

    /**
     * Documentos origen múltiples (para documentos agrupados)
     */
    public function documentosOrigenMultiples(): BelongsToMany
    {
        return $this->belongsToMany(
            Documento::class,
            'documento_documento_origen',
            'documento_id',
            'documento_origen_id'
        )->withPivot('cantidad_procesada')->withTimestamps();
    }

    /**
     * Documentos derivados múltiples (documentos que tienen a este como uno de sus orígenes)
     */
    public function documentosDerivadosMultiples(): BelongsToMany
    {
        return $this->belongsToMany(
            Documento::class,
            'documento_documento_origen',
            'documento_origen_id',
            'documento_id'
        )->withPivot('cantidad_procesada')->withTimestamps();
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
     * Agrupar múltiples documentos en uno nuevo
     * 
     * @param array $documentosIds IDs de los documentos a agrupar
     * @param string $tipoDestino Tipo del documento resultante
     * @return self Nuevo documento agrupado
     */
    public static function agruparDesde(array $documentosIds, string $tipoDestino): self
    {
        if (empty($documentosIds)) {
            throw new \InvalidArgumentException('Debe proporcionar al menos un documento para agrupar');
        }

        $documentos = static::findMany($documentosIds);
        
        if ($documentos->isEmpty()) {
            throw new \InvalidArgumentException('No se encontraron documentos válidos para agrupar');
        }

        // Verificar que todos los documentos son del mismo tipo
        $tipos = $documentos->pluck('tipo')->unique();
        if ($tipos->count() > 1) {
            throw new \InvalidArgumentException('Todos los documentos deben ser del mismo tipo para agrupar');
        }

        // Verificar que todos los documentos son del mismo tercero
        $terceros = $documentos->pluck('tercero_id')->unique();
        if ($terceros->count() > 1) {
            throw new \InvalidArgumentException('Todos los documentos deben pertenecer al mismo cliente/proveedor');
        }

        DB::beginTransaction();
        try {
            // Crear el nuevo documento tomando como base el primero
            $primerDocumento = $documentos->first();
            $nuevoDocumento = new static([
                'tipo' => $tipoDestino,
                'serie' => $primerDocumento->serie,
                'fecha' => now(),
                'tercero_id' => $primerDocumento->tercero_id,
                'user_id' => auth()->id() ?? $primerDocumento->user_id,
                'estado' => 'borrador',
                'forma_pago_id' => $primerDocumento->forma_pago_id,
            ]);
            $nuevoDocumento->save();

            // Asociar todos los documentos origen
            $nuevoDocumento->documentosOrigenMultiples()->attach($documentosIds);

            // Copiar todas las líneas de todos los documentos
            $orden = 1;
            foreach ($documentos as $documento) {
                foreach ($documento->lineas as $linea) {
                    $nuevaLinea = $linea->replicate();
                    $nuevaLinea->documento_id = $nuevoDocumento->id;
                    $nuevaLinea->orden = $orden++;
                    $nuevaLinea->save();
                }
            }

            // Recalcular totales
            $nuevoDocumento->recalcularTotales();

            DB::commit();
            return $nuevoDocumento;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
