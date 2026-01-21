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

        $tipoReciboTarget = $factura->tipo === 'factura_compra' ? 'recibo_compra' : 'recibo';

        // Verificar si ya tiene recibos generados
        $recibosExistentes = Documento::where('documento_origen_id', $factura->id)
            ->where('tipo', $tipoReciboTarget)
            ->count();

        if ($recibosExistentes > 0) {
            throw new \InvalidArgumentException('Esta factura ya tiene recibos generados');
        }

        DB::beginTransaction();
        try {
            $recibos = collect();
            
            // Determinar el tipo de recibo según el tipo de factura
            // (Ya calculado arriba como $tipoReciboTarget)
            
            // Calcular vencimientos usando la forma de pago
            $fechaBase = $factura->fecha ?? now();
            $vencimientos = $factura->formaPago->calcularVencimientos($fechaBase, (float) $factura->total);

            foreach ($vencimientos as $index => $vencimiento) {
                $recibo = new Documento([
                    'tipo' => $tipoReciboTarget,
                    'serie' => $factura->serie,
                    'fecha' => $fechaBase,
                    'fecha_vencimiento' => $vencimiento['fecha_vencimiento'],
                    'tercero_id' => $factura->tercero_id,
                    'user_id' => auth()->id() ?? $factura->user_id,
                    'documento_origen_id' => $factura->id,
                    'estado' => $factura->tipo === 'factura_compra' ? 'pendiente' : 'borrador', // Estado inicial
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

        $tipoReciboTarget = $factura->tipo === 'factura_compra' ? 'recibo_compra' : 'recibo';

        DB::beginTransaction();
        try {
            // Eliminar recibos existentes (soft delete)
            Documento::where('documento_origen_id', $factura->id)
                ->where('tipo', $tipoReciboTarget)
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
     * Marcar un recibo como cobrado/pagado
     * 
     * @param Documento $recibo
     * @param Carbon|null $fechaCobro
     * @return bool
     */
    public function marcarComoCobrado(Documento $recibo, ?Carbon $fechaCobro = null): bool
    {
        if (!in_array($recibo->tipo, ['recibo', 'recibo_compra'])) {
            throw new \InvalidArgumentException('Solo se pueden marcar recibos como cobrados/pagados');
        }

        $estadoFinal = $recibo->tipo === 'recibo_compra' ? 'pagado' : 'cobrado';

        $recibo->update([
            'estado' => $estadoFinal,
            'fecha_vencimiento' => $fechaCobro ?? now(), // Quizás fecha_cobro si existe columna
        ]);

        return true;
    }
}
