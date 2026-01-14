<?php

namespace App\Services;

use App\Models\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para la agrupación de múltiples documentos en uno nuevo
 */
class AgrupacionDocumentosService
{
    /**
     * Agrupar múltiples pedidos en un albarán
     * 
     * @param array $pedidosIds IDs de los pedidos a agrupar
     * @return Documento Albarán creado
     */
    public function agruparPedidosEnAlbaran(array $pedidosIds): Documento
    {
        $this->validarAgrupacion($pedidosIds, 'pedido', 'albaran');
        
        return Documento::agruparDesde($pedidosIds, 'albaran');
    }

    /**
     * Agrupar múltiples albaranes en una factura
     * 
     * @param array $albaranesIds IDs de los albaranes a agrupar
     * @return Documento Factura creada
     */
    public function agruparAlbaranesEnFactura(array $albaranesIds): Documento
    {
        $this->validarAgrupacion($albaranesIds, 'albaran', 'factura');
        
        return Documento::agruparDesde($albaranesIds, 'factura');
    }

    /**
     * Validar que los documentos pueden ser agrupados
     * 
     * @param array $documentosIds IDs de documentos a validar
     * @param string $tipoOrigen Tipo de documento origen esperado
     * @param string $tipoDestino Tipo de documento destino
     * @return bool
     * @throws \InvalidArgumentException Si la validación falla
     */
    public function validarAgrupacion(array $documentosIds, string $tipoOrigen, string $tipoDestino): bool
    {
        if (empty($documentosIds)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un documento para agrupar');
        }

        if (count($documentosIds) < 2) {
            throw new \InvalidArgumentException('Debe seleccionar al menos 2 documentos para agrupar');
        }

        $documentos = Documento::findMany($documentosIds);

        if ($documentos->count() !== count($documentosIds)) {
            throw new \InvalidArgumentException('Algunos documentos seleccionados no existen');
        }

        // Verificar que todos son del tipo correcto
        $tiposIncorrectos = $documentos->where('tipo', '!=', $tipoOrigen);
        if ($tiposIncorrectos->isNotEmpty()) {
            throw new \InvalidArgumentException("Todos los documentos deben ser del tipo '{$tipoOrigen}'");
        }

        // Verificar que todos pertenecen al mismo tercero
        $terceros = $documentos->pluck('tercero_id')->unique();
        if ($terceros->count() > 1) {
            throw new \InvalidArgumentException('Todos los documentos deben pertenecer al mismo cliente/proveedor');
        }

        // Verificar que ninguno está anulado
        $anulados = $documentos->where('estado', 'anulado');
        if ($anulados->isNotEmpty()) {
            throw new \InvalidArgumentException('No se pueden agrupar documentos anulados');
        }

        // Verificar que ninguno tiene documentos derivados (para evitar duplicados)
        foreach ($documentos as $documento) {
            if ($documento->tieneDocumentosDerivados()) {
                throw new \InvalidArgumentException(
                    "El documento {$documento->numero} ya tiene documentos derivados y no puede ser agrupado"
                );
            }
        }

        // Verificar que el flujo de conversión es válido
        $flujosValidos = [
            'pedido' => ['albaran'],
            'albaran' => ['factura'],
        ];

        if (!isset($flujosValidos[$tipoOrigen]) || !in_array($tipoDestino, $flujosValidos[$tipoOrigen])) {
            throw new \InvalidArgumentException(
                "No se puede agrupar documentos de tipo '{$tipoOrigen}' en '{$tipoDestino}'"
            );
        }

        return true;
    }

    /**
     * Obtener documentos agrupables para un tercero
     * 
     * @param string $tipo Tipo de documento
     * @param int $terceroId ID del tercero
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerDocumentosAgrupables(string $tipo, int $terceroId)
    {
        return Documento::where('tipo', $tipo)
            ->where('tercero_id', $terceroId)
            ->whereIn('estado', ['confirmado', 'parcial'])
            ->whereDoesntHave('documentosDerivados')
            ->whereDoesntHave('documentosDerivadosMultiples')
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Verificar si un conjunto de documentos puede desagruparse
     * 
     * @param Documento $documento Documento agrupado
     * @return bool
     */
    public function puedeDesagruparse(Documento $documento): bool
    {
        // Solo se pueden desagrupar documentos que tengan múltiples orígenes
        if ($documento->documentosOrigenMultiples->count() < 2) {
            return false;
        }

        // No se puede desagrupar si tiene documentos derivados
        if ($documento->tieneDocumentosDerivados()) {
            return false;
        }

        return true;
    }

    /**
     * Desagrupar un documento (eliminar el documento agrupado)
     * 
     * @param Documento $documento
     * @return bool
     */
    public function desagrupar(Documento $documento): bool
    {
        if (!$this->puedeDesagruparse($documento)) {
            throw new \InvalidArgumentException('Este documento no puede ser desagrupado');
        }

        DB::beginTransaction();
        try {
            // Simplemente eliminamos el documento agrupado
            // Los documentos origen quedarán libres para ser agrupados nuevamente
            $documento->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
