<?php

namespace App\Traits;

use Illuminate\Support\Collection;

/**
 * Trait para gestionar el bloqueo de documentos encadenados
 * 
 * Este trait implementa la lógica de negocio para determinar si un documento
 * puede ser editado o eliminado según su posición en la cadena de documentos.
 */
trait BloqueoDocumentos
{
    /**
     * Determinar si el documento puede editarse
     * 
     * Reglas:
     * - Si tiene documentos derivados, NO se puede editar
     * - Si no tiene documento origen, SÍ se puede editar (primera pieza de la cadena)
     * - Excepción: Pedido procedente de Presupuesto SÍ se puede editar
     * - Excepción: Documentos agrupados (múltiples orígenes) SÍ se pueden editar
     * - Si tiene un solo documento origen (no presupuesto), NO se puede editar
     */
    public function puedeEditarse(): bool
    {
        // 1. Si tiene documentos derivados activos, NO se puede editar nunca
        if ($this->tieneDocumentosDerivados()) {
            return false;
        }

        // 2. Si el estado es borrador, SÍ se puede editar siempre (si no tiene derivados)
        if (strtolower($this->estado) === 'borrador') {
            return true;
        }

        // 3. Si el estado es procesado, anulado o pagado, NO se edita
        if (in_array(strtolower($this->estado), ['procesado', 'anulado', 'pagado'])) {
            return false;
        }

        // 4. Regla de Oro para Facturas: Si tiene número (está confirmada), NO se edita jamás
        if (in_array($this->tipo, ['factura', 'factura_compra']) && !empty($this->numero)) {
            return false;
        }
        
        // 5. Excepciones para Pedidos procedentes de Presupuestos
        if ($this->tipo === 'pedido' && $this->documentoOrigen?->tipo === 'presupuesto') {
            return true;
        }
        
        // 6. Por defecto, si está confirmado y viene de un origen, bloqueamos
        if ($this->documento_origen_id && $this->estado === 'confirmado') {
            return false;
        }
        
        return true;
    }

    /**
     * Determinar si el documento puede eliminarse
     */
    public function puedeEliminarse(): bool
    {
        // 1. No se elimina si está anulado o procesado
        if (in_array(strtolower($this->estado), ['procesado', 'anulado'])) {
            return false;
        }

        // 2. Una factura PAGADA no se puede eliminar (debe anularse)
        if (strtolower($this->estado) === 'pagado') {
            return false;
        }

        // 3. Regla de Oro para Facturas: Si tiene número, no se elimina jamás
        if (in_array($this->tipo, ['factura', 'factura_compra']) && !empty($this->numero)) {
            return false;
        }

        // 4. Si es una factura en borrador (sin número), permitimos eliminar aunque tenga recibos
        // (porque los borraremos en cascada en el hook deleting)
        if (in_array($this->tipo, ['factura', 'factura_compra']) && empty($this->numero)) {
            return true;
        }

        return !$this->tieneDocumentosDerivados();
    }

    /**
     * Boot trait para manejar eliminación y desbloqueo
     */
    public static function bootBloqueoDocumentos()
    {
        static::deleting(function ($model) {
            // 1. REVERTIR STOCK al eliminar (si no estaba anulado ya)
            if ($model->stock_actualizado && $model->estado !== 'anulado') {
                $stockService = new \App\Services\StockService();
                $stockService->actualizarStockDesdeDocumento($model, true);
            }

            // 2. BORRADO EN CASCADA DE RECIBOS para facturas (Venta y Compra)
            if (in_array($model->tipo, ['factura', 'factura_compra'])) {
                // Buscamos derivados que sean recibos
                $recibos = $model->documentosDerivados()
                    ->whereIn('tipo', ['recibo', 'recibo_compra'])
                    ->get();
                
                foreach ($recibos as $recibo) {
                    $recibo->delete();
                }
            }
        });

        static::deleted(function ($model) {
            // 1. DESBLOQUEAR EL ORIGEN (Relación Simple)
            if ($model->documento_origen_id) {
                $origen = $model->documentoOrigen;
                if ($origen && $origen->estado === 'procesado') {
                    // Solo si no hay otros derivados activos aparte de este que estamos borrando
                    if (!$origen->tieneDocumentosDerivados()) {
                        $origen->update(['estado' => 'confirmado']);
                    }
                }
            }

            // 2. DESBLOQUEAR ORÍGENES MÚLTIPLES (Relación Many-to-Many - Agrupaciones)
            if ($model->documentosOrigenMultiples()->exists()) {
                foreach ($model->documentosOrigenMultiples as $origen) {
                    if ($origen->estado === 'procesado') {
                        // Verificamos si este origen todavía tiene otros derivados múltiples activos
                        // (menos el que acabamos de borrar, que ya no aparecerá en la relación)
                        if (!$origen->tieneDocumentosDerivados()) {
                            $origen->update(['estado' => 'confirmado']);
                        }
                    }
                }
                // Desligar orígenes para limpieza (aunque el registro se borre, esto es buena práctica)
                $model->documentosOrigenMultiples()->detach();
            }
        });
    }

    /**
     * Verificar si tiene documentos derivados
     */
    public function tieneDocumentosDerivados(): bool
    {
        // Verificar documentos derivados con relación simple (ignorando etiquetas y anulados)
        if ($this->documentosDerivados()
            ->where('tipo', '!=', 'etiqueta')
            ->where('estado', '!=', 'anulado')
            ->exists()) {
            return true;
        }
        
        // Verificar documentos derivados en relación múltiple (ignorando etiquetas y anulados)
        if ($this->documentosDerivadosMultiples()
            ->where('tipo', '!=', 'etiqueta')
            ->where('estado', '!=', 'anulado')
            ->exists()) {
            return true;
        }
        
        return false;
    }

    /**
     * Obtener los documentos que están bloqueando la edición
     * 
     * @return Collection Colección de documentos bloqueantes
     */
    public function getDocumentosBloqueantes(): Collection
    {
        $bloqueantes = collect();
        
        // Si tiene documentos derivados, esos son los bloqueantes
        if ($this->tieneDocumentosDerivados()) {
            $derivadosSimples = $this->documentosDerivados()
                ->where('tipo', '!=', 'etiqueta')
                ->where('estado', '!=', 'anulado')
                ->get();
            $derivadosMultiples = $this->documentosDerivadosMultiples()
                ->where('tipo', '!=', 'etiqueta')
                ->where('estado', '!=', 'anulado')
                ->get();
            
            $bloqueantes = $derivadosSimples->merge($derivadosMultiples)->unique('id');
        }
        
        return $bloqueantes;
    }

    /**
     * Obtener mensaje explicativo del bloqueo
     * 
     * @return string|null Mensaje de bloqueo o null si no está bloqueado
     */
    public function getMensajeBloqueo(): ?string
    {
        if ($this->puedeEditarse()) {
            return null;
        }
        
        $bloqueantes = $this->getDocumentosBloqueantes();
        
        if ($bloqueantes->isEmpty()) {
            return null;
        }
        
        if ($bloqueantes->count() === 1) {
            $bloqueante = $bloqueantes->first();
            return "Este documento no puede editarse porque ya se generó el {$this->getNombreTipo($bloqueante->tipo)} {$bloqueante->numero}. Para modificar este documento, primero elimina el {$this->getNombreTipo($bloqueante->tipo)}.";
        }
        
        $tipos = $bloqueantes->pluck('tipo')->unique()->map(fn($tipo) => $this->getNombreTipo($tipo))->join(', ');
        return "Este documento no puede editarse porque ya se generaron {$bloqueantes->count()} documentos derivados ({$tipos}). Para modificar este documento, primero elimina los documentos derivados.";
    }

    /**
     * Obtener mensaje corto del bloqueo (para tooltips)
     * 
     * @return string|null Mensaje corto o null si no está bloqueado
     */
    public function getMensajeBloqueoCorto(): ?string
    {
        if ($this->puedeEditarse()) {
            return null;
        }
        
        $bloqueantes = $this->getDocumentosBloqueantes();
        
        if ($bloqueantes->isEmpty()) {
            return 'Bloqueado';
        }
        
        if ($bloqueantes->count() === 1) {
            $bloqueante = $bloqueantes->first();
            return "Bloqueado por {$this->getNombreTipo($bloqueante->tipo)} {$bloqueante->numero}";
        }
        
        return "Bloqueado por {$bloqueantes->count()} documentos derivados";
    }

    /**
     * Obtener el nombre legible del tipo de documento
     */
    protected function getNombreTipo(string $tipo): string
    {
        $nombres = [
            'presupuesto' => 'Presupuesto',
            'pedido' => 'Pedido',
            'albaran' => 'Albarán',
            'factura' => 'Factura',
            'recibo' => 'Recibo',
        ];
        
        return $nombres[$tipo] ?? ucfirst($tipo);
    }

    /**
     * Obtener icono para el estado de bloqueo
     */
    public function getIconoBloqueo(): string
    {
        if ($this->tieneDocumentosDerivados()) {
            return 'heroicon-o-lock-closed';
        }
        
        if (!$this->puedeEditarse()) {
            return 'heroicon-o-link';
        }
        
        return 'heroicon-o-lock-open';
    }

    /**
     * Obtener color para el estado de bloqueo
     */
    public function getColorBloqueo(): string
    {
        if ($this->tieneDocumentosDerivados()) {
            return 'danger';
        }
        
        if (!$this->puedeEditarse()) {
            return 'warning';
        }
        
        return 'success';
    }
}
