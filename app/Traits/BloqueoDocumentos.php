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
        // 1. Si tiene documentos derivados, NO se puede editar nunca
        // (Ignoramos etiquetas para el bloqueo de edición del albarán)
        if ($this->tieneDocumentosDerivados()) {
            return false;
        }

        // 2. Si el estado es borrador, SÍ se puede editar siempre (si no tiene derivados)
        if (strtolower($this->estado) === 'borrador') {
            return true;
        }

        // 3. Regla de Oro para Facturas: Si tiene número (está confirmada), NO se edita jamás
        // EXCEPCIÓN: Las facturas de compra se permiten editar para ajustes
        if ($this->tipo === 'factura' && !empty($this->numero)) {
            return false;
        }
        
        // 4. Si no tiene documento origen, sí se puede editar
        if (!$this->documento_origen_id && $this->documentosOrigenMultiples->isEmpty()) {
            return true;
        }
        
        // 5. Excepciones de cadena
        if ($this->tipo === 'pedido' && $this->documentoOrigen?->tipo === 'presupuesto') {
            return true;
        }
        
        if ($this->documentosOrigenMultiples->count() > 1) {
            return true;
        }
        
        // 6. Por defecto, si viene de otro documento y no es borrador, bloqueamos para mantener la integridad
        if ($this->documento_origen_id) {
            return false;
        }
        
        return true;
    }

    /**
     * Determinar si el documento puede eliminarse
     * 
     * Un documento solo puede eliminarse si no tiene documentos derivados.
     * Las facturas confirmadas JAMÁS se eliminan.
     */
    public function puedeEliminarse(): bool
    {
        // Regla de Oro para Facturas: Si tiene número (está confirmada), NO se elimina jamás
        // EXCEPCIÓN: Las facturas de compra SÍ se pueden eliminar para revertir flujos
        if ($this->tipo === 'factura' && !empty($this->numero)) {
            return false;
        }

        return !$this->tieneDocumentosDerivados();
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
