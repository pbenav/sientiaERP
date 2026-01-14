<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\FormaPago;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para la generación automática de recibos desde facturas
 */
class RecibosService
{
    /**
     * Generar recibos automáticamente desde una factura
     * 
     * @param Documento $factura La factura desde la que generar recibos
     * @return Collection Colección de recibos generados
     * @throws \InvalidArgumentException Si el documento no es una factura
     */
    public function generarRecibosDesdeFactura(Documento $factura): Collection
    {
        if (!in_array($factura->tipo, ['factura', 'factura_compra'])) {
            throw new \InvalidArgumentException('Solo se pueden generar recibos desde facturas');
        }


        if (!$factura->formaPago) {
            throw new \InvalidArgumentException('La factura debe tener una forma de pago asignada');
        }

        // Verificar si ya tiene recibos generados
        $recibosExistentes = Documento::where('documento_origen_id', $factura->id)
            ->where('tipo', 'recibo')
            ->count();

        if ($recibosExistentes > 0) {
            throw new \InvalidArgumentException('Esta factura ya tiene recibos generados');
        }

        DB::beginTransaction();
        try {
            $recibos = collect();
            
            // Calcular vencimientos usando la forma de pago
            $fechaBase = $factura->fecha ?? now();
            $vencimientos = $factura->formaPago->calcularVencimientos($fechaBase, (float) $factura->total);

            foreach ($vencimientos as $index => $vencimiento) {
                $recibo = new Documento([
                    'tipo' => 'recibo',
                    'serie' => $factura->serie,
                    'fecha' => $fechaBase,
                    'fecha_vencimiento' => $vencimiento['fecha_vencimiento'],
                    'tercero_id' => $factura->tercero_id,
                    'user_id' => auth()->id() ?? $factura->user_id,
                    'documento_origen_id' => $factura->id,
                    'estado' => 'pendiente', // Estado para recibos: pendiente, cobrado, anulado
                    'subtotal' => $vencimiento['importe'],
                    'base_imponible' => $vencimiento['importe'],
                    'total' => $vencimiento['importe'],
                    'forma_pago_id' => $factura->forma_pago_id,
                    'observaciones' => "Recibo " . ($index + 1) . " de " . count($vencimientos) . 
                                     " de la factura {$factura->numero}",
                ]);

                $recibo->save();
                $recibos->push($recibo);
            }

            DB::commit();
            return $recibos;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calcular fechas de vencimiento según la forma de pago
     * 
     * @param FormaPago $formaPago Forma de pago con tramos
     * @param Carbon $fechaBase Fecha base para el cálculo
     * @return array Array de fechas de vencimiento
     */
    public function calcularFechasVencimiento(FormaPago $formaPago, Carbon $fechaBase): array
    {
        $fechas = [];
        
        if (empty($formaPago->tramos)) {
            $fechas[] = $fechaBase->copy();
            return $fechas;
        }

        foreach ($formaPago->tramos as $tramo) {
            $dias = $tramo['dias'] ?? 0;
            $fechas[] = $fechaBase->copy()->addDays($dias);
        }

        return $fechas;
    }

    /**
     * Regenerar recibos de una factura (elimina los existentes y crea nuevos)
     * 
     * @param Documento $factura
     * @return Collection
     */
    public function regenerarRecibos(Documento $factura): Collection
    {
        if (!in_array($factura->tipo, ['factura', 'factura_compra'])) {
            throw new \InvalidArgumentException('Solo se pueden regenerar recibos desde facturas');
        }


        DB::beginTransaction();
        try {
            // Eliminar recibos existentes (soft delete)
            Documento::where('documento_origen_id', $factura->id)
                ->where('tipo', 'recibo')
                ->delete();

            // Generar nuevos recibos
            $recibos = $this->generarRecibosDesdeFactura($factura);

            DB::commit();
            return $recibos;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Marcar un recibo como cobrado
     * 
     * @param Documento $recibo
     * @param Carbon|null $fechaCobro
     * @return bool
     */
    public function marcarComoCobrado(Documento $recibo, ?Carbon $fechaCobro = null): bool
    {
        if ($recibo->tipo !== 'recibo') {
            throw new \InvalidArgumentException('Solo se pueden marcar recibos como cobrados');
        }

        $recibo->update([
            'estado' => 'cobrado',
            'fecha_vencimiento' => $fechaCobro ?? now(),
        ]);

        return true;
    }
}
