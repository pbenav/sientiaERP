<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class FormaPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'formas_pago';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'tramos',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'tramos' => 'array',
        'activo' => 'boolean',
    ];

    /**
     * Asegurar que tramos siempre devuelva un array (mecanismo de seguridad adicional al cast)
     */
    public function getTramosAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Documentos que usan esta forma de pago
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /**
     * Calcular vencimientos a partir de una fecha base y un importe
     * 
     * @param Carbon $fechaBase Fecha desde la que calcular (normalmente fecha de factura)
     * @param float $importe Importe total a dividir según los tramos
     * @return Collection Colección de arrays con 'fecha_vencimiento' e 'importe'
     */
    public function calcularVencimientos(Carbon $fechaBase, float $importe): Collection
    {
        $vencimientos = collect();
        $tramos = $this->tramos;
        
        if (empty($tramos)) {
            // Si no hay tramos definidos, un solo pago al contado
            $vencimientos->push([
                'fecha_vencimiento' => $fechaBase->copy(),
                'importe' => $importe,
                'porcentaje' => 100,
                'dias' => 0,
            ]);
            
            return $vencimientos;
        }

        $totalPorcentaje = 0;
        
        foreach ($tramos as $index => $tramo) {
            $dias = $tramo['dias'] ?? 0;
            $porcentaje = $tramo['porcentaje'] ?? 0;
            
            $totalPorcentaje += $porcentaje;
            
            // Calcular fecha de vencimiento
            $fechaVencimiento = $fechaBase->copy()->addDays($dias);
            
            // Calcular importe de este tramo
            // En el último tramo, ajustar para que sume exactamente el total (evitar errores de redondeo)
            if ($index === count($tramos) - 1) {
                $importeTramo = $importe - $vencimientos->sum('importe');
            } else {
                $importeTramo = round($importe * ($porcentaje / 100), 2);
            }
            
            $vencimientos->push([
                'fecha_vencimiento' => $fechaVencimiento,
                'importe' => $importeTramo,
                'porcentaje' => $porcentaje,
                'dias' => $dias,
            ]);
        }

        return $vencimientos;
    }

    /**
     * Verificar si es pago al contado (0 días)
     */
    public function esContado(): bool
    {
        $tramos = $this->tramos;
        if (empty($tramos)) {
            return true;
        }

        return count($tramos) === 1 && ($tramos[0]['dias'] ?? 0) === 0;
    }

    /**
     * Obtener el número de tramos/plazos
     */
    public function getNumeroTramosAttribute(): int
    {
        return count($this->tramos);
    }

    /**
     * Obtener el plazo máximo en días
     */
    public function getPlazoMaximoAttribute(): int
    {
        $tramos = $this->tramos;
        
        if (empty($tramos)) {
            return 0;
        }

        return max(array_column($tramos, 'dias'));
    }

    /**
     * Scope para formas de pago activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope por tipo
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
