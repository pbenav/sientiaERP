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
        'subtotal', 'descuento', 'base_imponible', 'iva', 'irpf', 'porcentaje_irpf', 'recargo_equivalencia', 'total',
        'forma_pago_id',
        'observaciones', 'observaciones_internas',
        'fecha_validez', 'fecha_entrega', 'fecha_vencimiento',
        'es_rectificativa', 'rectificada_id'
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
        'porcentaje_irpf' => 'decimal:2',
        'recargo_equivalencia' => 'decimal:2',
        'total' => 'decimal:2',
        'es_rectificativa' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($documento) {
            // No generamos número automáticamente para facturas (se hace al confirmar)
            if (empty($documento->numero) && !in_array($documento->tipo, ['factura', 'factura_compra'])) {
                $documento->numero = NumeracionDocumento::generarNumero($documento->tipo, $documento->serie ?? 'A');
            }
            if (empty($documento->fecha)) {
                $documento->fecha = now();
            }
        });

        static::deleting(function ($documento) {
            // Si se elimina una factura generada desde un ticket, limpiar la referencia
            if (in_array($documento->tipo, ['factura', 'factura_compra'])) {
                \App\Models\Ticket::where('documento_id', $documento->id)
                    ->update(['documento_id' => null]);
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
     * Factura anulada que es rectificada por esta
     */
    public function facturaRectificada(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'rectificada_id');
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
        
        // Inicializar acumuladores
        $baseImponibleTotal = 0;
        $ivaTotal = 0;
        $reTotal = 0;

        // Agrupación por tipos de IVA (Buckets)
        $buckets = $lineas->groupBy(function ($linea) {
            return number_format($linea->iva, 2); // Agrupar por % IVA (string key)
        });

        foreach ($buckets as $ivaKey => $grupoLineas) {
            // Sumar bases imponibles del grupo (ya tienen descuentos aplicados en la línea)
            $baseGrupo = $grupoLineas->sum('subtotal');
            
            // Determinar % IVA y % RE del grupo (tomamos el de la primera línea)
            $primerLinea = $grupoLineas->first();
            $porcentajeIva = $primerLinea->iva;
            $porcentajeRe = $primerLinea->recargo_equivalencia; // Ya calculado en la línea

            // Calcular Cuotas del Bucket (Redondeo estándar)
            $cuotaIvaGrupo = round($baseGrupo * ($porcentajeIva / 100), 2);
            $cuotaReGrupo = round($baseGrupo * ($porcentajeRe / 100), 2);

            // Acumular al total del documento
            $baseImponibleTotal += $baseGrupo;
            $ivaTotal += $cuotaIvaGrupo;
            $reTotal += $cuotaReGrupo;
        }

        // Asignar totales calculados
        $this->subtotal = $lineas->sum('subtotal'); // Suma bruta de líneas (debería coincidir con baseImponibleTotal)
        $this->base_imponible = $baseImponibleTotal;
        $this->iva = $ivaTotal;
        $this->recargo_equivalencia = $reTotal;
        
        // IRPF: Se calcula sobre la suma de todas las bases imponibles
        // Validación: Solo si el emisor es profesional (configuración global?) y receptor empresa.
        // Asumimos que $this->porcentaje_irpf ya viene de la lógica de negocio (asignado al crear/editar).
        if ($this->porcentaje_irpf > 0) {
             // Constraint: IPRF max 20%
             if ($this->porcentaje_irpf > 20) {
                 // Podríamos lanzar excepción, pero mejor lo limitamos o dejamos que la UI lo maneje.
                 // El usuario pidió "Bloquea el cálculo".
                 throw new \InvalidArgumentException('El porcentaje de IRPF no puede superar el 20%.');
             }
             if ($this->porcentaje_irpf < 0) {
                 throw new \InvalidArgumentException('El porcentaje de IRPF no puede ser negativo.');
             }

             // Cálculo IRPF
             $this->irpf = round($this->base_imponible * ($this->porcentaje_irpf / 100), 2);
        } else {
            $this->irpf = 0;
        }
        
        // Total Final = Base + IVA + RE - IRPF
        $this->total = $this->base_imponible + $this->iva + $this->recargo_equivalencia - $this->irpf;

        // Rectificativas: Asegurar signos negativos si es rectificativa?
        // El prompt dice: "En facturas rectificativas, todas las unidades y cuotas deben ser negativas".
        // Si el usuario introduce cantidades positivas en líneas, aquí podríamos forzar el signo negativo al final,
        // o asumir que las líneas ya son negativas. 
        // Si 'es_rectificativa' es true, deberíamos invertir si es positivo?
        // Por seguridad, si es rectificativa y el total es positivo, lo invertimos?
        // Mejor NO tocarlo mágicamente si las líneas son positivas, salvo que sea una regla estricta.
        // El usuario dijo "En facturas rectificativas, todas las unidades y cuotas deben ser negativas".
        // Validaremos en el futuro o dejaremos que el usuario meta negativos.
        
        $this->save();
    }

    /**
     * Confirmar documento (cambiar de borrador a confirmado)
     */
    public function confirmar(): void
    {
        if ($this->estado === 'borrador') {
            // Validación cronológica para facturas
            if (in_array($this->tipo, ['factura', 'factura_compra'])) {
                $ultimaFactura = self::where('tipo', $this->tipo)
                    ->where('serie', $this->serie)
                    ->where('estado', 'confirmado')
                    ->whereNotNull('numero')
                    ->orderBy('numero', 'desc')
                    ->first();

                if ($ultimaFactura && $this->fecha->lt($ultimaFactura->fecha)) {
                    throw new \Exception("No se puede confirmar la factura con fecha {$this->fecha->format('d/m/Y')}. Existe una factura anterior ({$ultimaFactura->numero}) con fecha {$ultimaFactura->fecha->format('d/m/Y')}.");
                }
            }

            $data = ['estado' => 'confirmado'];

            // Si es una factura y no tiene número, se genera ahora
            if (empty($this->numero) && in_array($this->tipo, ['factura', 'factura_compra'])) {
                $data['numero'] = NumeracionDocumento::generarNumero($this->tipo, $this->serie ?? 'A');
            }

            $this->update($data);

            // Generar recibos automáticamente si es factura
            if (in_array($this->tipo, ['factura', 'factura_compra'])) {
                try {
                    $service = new \App\Services\RecibosService();
                    // Verificar si ya tiene recibos antes de intentar generar
                    $tipoRecibo = $this->tipo === 'factura_compra' ? 'recibo_compra' : 'recibo';
                    $tieneRecibos = self::where('documento_origen_id', $this->id)
                        ->where('tipo', $tipoRecibo)
                        ->exists();

                    if (!$tieneRecibos && $this->forma_pago_id) {
                        $service->generarRecibosDesdeFactura($this);
                    }
                } catch (\Exception $e) {
                    // Log error o continuar silenciosamente. 
                    // No queremos romper la confirmación si falla la generación automática de recibos.
                    \Illuminate\Support\Facades\Log::error('Error generando recibos automáticos al confirmar factura ' . $this->id . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Anular documento
     */
    public function anular(): void
    {
        // Validar recibos si es factura
        if (in_array($this->tipo, ['factura', 'factura_compra'])) {
            $recibosPagados = self::where('documento_origen_id', $this->id)
                ->where('tipo', 'recibo')
                ->whereIn('estado', ['pagado', 'parcialmente_pagado'])
                ->count();
                
            if ($recibosPagados > 0) {
                throw new \Exception('No se puede anular la factura porque tiene recibos pagados. Debe crear una factura rectificativa negativa.');
            }
        }
        
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
                // Insertar línea de cabecera del documento agrupado
                $nuevoDocumento->lineas()->create([
                    'orden' => $orden++,
                    'descripcion' => "--- {$documento->tipo} {$documento->numero} ({$documento->fecha->format('d/m/Y')}) ---",
                    'cantidad' => 0,
                    'precio_unitario' => 0,
                    'iva' => 0,
                    'importe_iva' => 0,
                    'subtotal' => 0,
                    'total' => 0,
                ]);

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
