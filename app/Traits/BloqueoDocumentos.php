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

        // 3. Si el estado es procesado o anulado, NO se edita
        if (in_array(strtolower($this->estado), ['procesado', 'anulado'])) {
            return false;
        }

        // 4. Regla de Oro para Facturas: Si tiene número (está confirmada), NO se edita jamás
        if ($this->tipo === 'factura' && !empty($this->numero)) {
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
        // No se elimina si está anulado o procesado
        if (in_array(strtolower($this->estado), ['procesado', 'anulado'])) {
            return false;
        }

        // Regla de Oro para Facturas
        if ($this->tipo === 'factura' && !empty($this->numero)) {
            return false;
        }

        return !$this->tieneDocumentosDerivados();
    }

    /**
     * Boot trait para manejar eliminación y desbloqueo
     */
    public static function bootBloqueoDocumentos()
    {
        static::deleting(function ($model) {
            // REVERTIR STOCK al eliminar (si no estaba anulado ya)
            if ($model->stock_actualizado && $model->estado !== 'anulado') {
                $stockService = new \App\Services\StockService();
                $stockService->actualizarStockDesdeDocumento($model, true);
            }
        });

        static::deleted(function ($model) {
            // DESBLOQUEAR EL ORIGEN
            if ($model->documento_origen_id) {
                $origen = $model->documentoOrigen;
                if ($origen && $origen->estado === 'procesado') {
                    // Solo si no hay otros derivados activos aparte de este que estamos borrando
                    if (!$origen->tieneDocumentosDerivados()) {
                        $origen->update(['estado' => 'confirmado']);
                    }
                }
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
