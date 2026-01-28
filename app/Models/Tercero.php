<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tercero extends Model
{
    use HasFactory, SoftDeletes, HasUppercaseDisplay;

    protected $fillable = [
        'codigo', 'nombre_comercial', 'razon_social', 'nif_cif',
        'email', 'telefono', 'movil', 'web', 'persona_contacto',
        'direccion_fiscal', 'codigo_postal_fiscal', 'poblacion_fiscal',
        'provincia_fiscal', 'pais_fiscal',
        'direccion_envio_diferente',
        'direccion_envio', 'codigo_postal_envio', 'poblacion_envio',
        'provincia_envio', 'pais_envio',
        'iban', 'swift', 'banco',
        'forma_pago', 'forma_pago_id', 'dias_pago', 'descuento_comercial', 'limite_credito',
        'recargo_equivalencia', 'irpf',
        'activo', 'observaciones'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'direccion_envio_diferente' => 'boolean',
        'recargo_equivalencia' => 'boolean',
        'descuento_comercial' => 'decimal:2',
        'limite_credito' => 'decimal:2',
        'irpf' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tercero) {
            if (empty($tercero->codigo)) {
                $tercero->codigo = static::generarCodigo();
            }
        });
    }

    /**
     * Tipos de tercero (cliente, proveedor, etc.)
     */
    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(TipoTercero::class, 'tercero_tipo')
            ->withTimestamps();
    }

    /**
     * Documentos del tercero
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /**
     * Forma de pago por defecto del tercero
     */
    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    /**
     * Verificar si es cliente
     */
    public function esCliente(): bool
    {
        return $this->tipos()->where('codigo', 'CLI')->exists();
    }

    /**
     * Verificar si es proveedor
     */
    public function esProveedor(): bool
    {
        return $this->tipos()->where('codigo', 'PRO')->exists();
    }

    /**
     * Obtener dirección de envío (fiscal si no tiene específica)
     */
    public function getDireccionEnvioCompletaAttribute(): string
    {
        if ($this->direccion_envio_diferente && $this->direccion_envio) {
            return "{$this->direccion_envio}, {$this->codigo_postal_envio} {$this->poblacion_envio}, {$this->provincia_envio}, {$this->pais_envio}";
        }

        return "{$this->direccion_fiscal}, {$this->codigo_postal_fiscal} {$this->poblacion_fiscal}, {$this->provincia_fiscal}, {$this->pais_fiscal}";
    }

    /**
     * Obtener dirección fiscal completa
     */
    public function getDireccionFiscalCompletaAttribute(): string
    {
        return "{$this->direccion_fiscal}, {$this->codigo_postal_fiscal} {$this->poblacion_fiscal}, {$this->provincia_fiscal}, {$this->pais_fiscal}";
    }

    /**
     * Generar código automático
     */
    private static function generarCodigo(): string
    {
        $ultimo = static::withTrashed()->max('id') ?? 0;
        return 'TER-' . str_pad($ultimo + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scopes
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeClientes($query)
    {
        return $query->whereHas('tipos', function ($q) {
            $q->where('codigo', 'CLI');
        });
    }

    public function scopeProveedores($query)
    {
        return $query->whereHas('tipos', function ($q) {
            $q->where('codigo', 'PRO');
        });
    }
}
