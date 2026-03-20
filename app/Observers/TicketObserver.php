<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\VerifactuService;
use Illuminate\Support\Facades\Log;

class TicketObserver
{
    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        // Si el ticket se ha completado y NO tiene huella Verifactu, generarla
        if ($ticket->isDirty('status') && 
            $ticket->status === 'completed' && 
            empty($ticket->verifactu_huella)) {
            
            try {
                $service = app(VerifactuService::class);
                $service->procesarEncadenamiento($ticket);
                
                // Opcional: Enviar automáticamente si está configurado (Por ahora manual o vía comando)
                // $service->enviarAEAT($ticket);
                
            } catch (\Exception $e) {
                Log::error("Error en encadenamiento Verifactu para Ticket {$ticket->numero}: " . $e->getMessage());
            }
        }
    }
}
