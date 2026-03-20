<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Documento;
use App\Models\Ticket;
use App\Services\VerifactuService;
use Carbon\Carbon;

class InitVerifactu extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'verifactu:init {--date= : Fecha de inicio (YYYY-MM-DD)} {--send : Enviar también a la AEAT (CUIDADO)}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Inicializa el encadenamiento Verifactu para facturas y tickets existentes a partir de una fecha.';

    /**
     * Ejecutar el comando de consola.
     */
    public function handle(VerifactuService $service)
    {
        if (!\App\Models\Setting::get('verifactu_active', false)) {
            $this->error("Veri*Factu está DESACTIVADO en la configuración global.");
            $this->line("Para inicializar el encadenamiento, actívalo primero en el panel de Configuración.");
            return;
        }

        $dateStr = $this->option('date') ?? now()->startOfMonth()->format('Y-m-d');
        $send = $this->option('send');
        $date = Carbon::parse($dateStr);

        $this->info("=== Iniciando Proceso Veri*Factu ===");
        $this->warn("Ajustando encadenamiento desde: " . $date->format('d/m/Y'));

        if ($send && !$this->confirm('¿Realmente deseas enviar estas facturas antiguas a la AEAT? Esto es irreversible.')) {
            $send = false;
        }

        // 1. Procesar Tickets (Simplificadas)
        $tickets = Ticket::where('created_at', '>=', $date)
            ->whereNull('verifactu_huella')
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($tickets->count() > 0) {
            $this->info("Procesando " . $tickets->count() . " tickets del TPV...");
            $bar = $this->output->createProgressBar($tickets->count());
            $bar->start();

            foreach ($tickets as $ticket) {
                $service->procesarEncadenamiento($ticket);
                if ($send) {
                    $service->enviarAEAT($ticket);
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
        } else {
            $this->line("No hay tickets pendientes desde esa fecha.");
        }

        // 2. Procesar Facturas (Documentos)
        // Nota: En Verifactu, cada serie/tipo puede tener su propia cadena.
        $docs = Documento::where('fecha', '>=', $date)
            ->whereIn('tipo', ['factura', 'factura_compra'])
            ->whereNull('verifactu_huella')
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($docs->count() > 0) {
            $this->info("Procesando " . $docs->count() . " facturas manuales...");
            $bar = $this->output->createProgressBar($docs->count());
            $bar->start();

            foreach ($docs as $doc) {
                // El servicio buscará automáticamente el anterior de su misma serie y tipo
                $service->procesarEncadenamiento($doc);
                if ($send) {
                    $service->enviarAEAT($doc);
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
        } else {
            $this->line("No hay facturas manuales pendientes desde esa fecha.");
        }

        $this->success("Proceso de inicialización Veri*Factu completado con éxito.");
    }

    protected function success($message)
    {
        $this->output->writeln("<info>✔</info> $message");
    }
}
