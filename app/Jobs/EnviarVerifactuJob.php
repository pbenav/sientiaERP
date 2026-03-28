<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use App\Services\VerifactuService;

class EnviarVerifactuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $model;

    /**
     * Número de reintentos antes de fallar.
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(VerifactuService $verifactuService): void
    {
        // Si el estado ya es 'accepted', no reenviamos (preventivo)
        if ($this->model->verifactu_status === 'Aceptado') {
            return;
        }

        $res = $verifactuService->enviarAEAT($this->model);

        if (!$res['success']) {
            // Un Business Error de la AEAT normalmente no se soluciona reintentando, 
            // pero si es un fallo de conexión tirará una excepción. 
            // Si $res['success'] es false pero no lanzó excepción, significa que 
            // la AEAT lo rechazó (Ej: error 1189, formato de NIF, etc.).
            // Documentamos el error pero no hacemos fail() para que no colapse la cola,
            // ya que el usuario debe rectificar manualmente.
            \Log::warning("VeriFactu Async Job completado con rechazo para ID {$this->model->id}: " . $res['error']);
        }
    }
}
