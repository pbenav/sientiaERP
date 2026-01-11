<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;

class TercerosActions
{
    private DetailsActions $details;

    public function __construct(ErpClient $client, Screen $screen, KeyHandler $keyHandler, DetailsActions $details)
    {
        $this->client = $client;
        $this->screen = $screen;
        $this->keyHandler = $keyHandler;
        $this->details = $details;
    }

    /**
     * Listar todos los terceros
     */
    public function listar(?string $tipo = null): void
    {
        $page = 1;
        $perPage = 15;
        $running = true;
        $selectedIndex = 0;
        $selectionFile = '/tmp/erp_tui_selection.json';

        $needsFetch = true;
        $needsRender = true;

        while ($running) {
            try {
                if ($needsFetch) {
                    $response = $this->client->getTerceros($page, $perPage, $tipo);
                    $terceros = $response['data'] ?? [];
                    $total = $response['total'] ?? 0;
                    $lastPage = $response['last_page'] ?? 1;
                    $needsFetch = false;
                    $needsRender = true;
                }

                if ($needsRender && !$this->keyHandler->hasPending()) {
                    $this->renderTercerosList($terceros, $page, $lastPage, $total, $tipo, $selectedIndex);
                    $needsRender = false;
                }

                $input = $this->keyHandler->waitForKey();
                if ($input === "") continue;

                if ($input === "\e[A") { // Up
                    if ($selectedIndex > 0) {
                        $selectedIndex--;
                        $needsRender = true;
                    }
                } elseif ($input === "\e[B") { // Down
                    if ($selectedIndex < count($terceros) - 1) {
                        $selectedIndex++;
                        $needsRender = true;
                    }
                } elseif ($input === "\n") { // Enter - Select
                    if (isset($terceros[$selectedIndex])) {
                        $this->details->renderTercero($terceros[$selectedIndex]['id']);
                        $needsRender = true; // Volver a pintar la lista al regresar
                    }
                }

                $key = strtolower($input);
                if ($key === 'n') {
                    $this->crear($tipo);
                    $needsFetch = true; // Recargar por si se creó uno nuevo
                } elseif ($key === 'e') {
                    if (isset($terceros[$selectedIndex])) {
                        $this->editar($terceros[$selectedIndex]['id']);
                        $needsFetch = true;
                    }
                } elseif ($key === 'p') {
                    if ($page > 1) {
                        $page--;
                        $selectedIndex = 0;
                        $needsFetch = true;
                    }
                } elseif ($key === 's') {
                    if ($page < $lastPage) {
                        $page++;
                        $selectedIndex = 0;
                        $needsFetch = true;
                    }
                } elseif ($input === "\e" || $input === "\x1b") {
                    $running = false;
                }
            } catch (\Exception $e) {
                $this->screen->clear();
                echo "\n\n  ❌ Error: " . $e->getMessage() . "\n\n";
                echo "  Presione cualquier tecla para continuar...";
                $this->keyHandler->read(5000);
                $running = false;
            }
        }
    }

    public function crear(?string $tipo = null): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   NUEVO TERCERO                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            // Usar Laravel Prompts para la entrada de datos
            $nombre = \Laravel\Prompts\text('Nombre Comercial/Razón Social', required: true);
            $nif = \Laravel\Prompts\text('NIF/CIF', required: true);
            $email = \Laravel\Prompts\text('Email', validate: fn($v) => empty($v) || filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email inválido');
            $telefono = \Laravel\Prompts\text('Teléfono');
            
            $tiposDisponibles = [
                'CLI' => 'Cliente',
                'PRO' => 'Proveedor',
                'EMP' => 'Empleado',
                'TRA' => 'Transportista'
            ];
            
            $tiposSeleccionados = \Laravel\Prompts\multiselect(
                'Tipos de Tercero',
                options: $tiposDisponibles,
                default: $tipo ? [strtoupper(substr($tipo, 0, 3))] : ['CLI'],
                required: true
            );

            $confirm = \Laravel\Prompts\confirm('¿Desea crear el tercero?', default: true);

            if ($confirm) {
                $data = [
                    'nombre_comercial' => $nombre,
                    'razon_social' => $nombre,
                    'nif_cif' => $nif,
                    'email' => $email,
                    'telefono' => $telefono,
                    'tipos' => $tiposSeleccionados
                ];

                $this->client->createTercero($data);
                \Laravel\Prompts\info('Tercero creado correctamente.');
                sleep(1);
            }
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error al crear tercero: ' . $e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }

    public function editar(int $id): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   EDITAR TERCERO                              ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            $tercero = $this->client->getTercero($id);

            $nombre = \Laravel\Prompts\text('Nombre Comercial', default: $tercero['nombre_comercial'] ?? '', required: true);
            $nif = \Laravel\Prompts\text('NIF/CIF', default: $tercero['nif_cif'] ?? '', required: true);
            $email = \Laravel\Prompts\text('Email', default: $tercero['email'] ?? '', validate: fn($v) => empty($v) || filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email inválido');
            $telefono = \Laravel\Prompts\text('Teléfono', default: $tercero['telefono'] ?? '');
            
            $tiposDisponibles = [
                'CLI' => 'Cliente',
                'PRO' => 'Proveedor',
                'EMP' => 'Empleado',
                'TRA' => 'Transportista'
            ];
            
            $tiposActuales = array_map(fn($t) => $t['codigo'], $tercero['tipos'] ?? []);
            
            $tiposSeleccionados = \Laravel\Prompts\multiselect(
                'Tipos de Tercero',
                options: $tiposDisponibles,
                default: !empty($tiposActuales) ? $tiposActuales : ['CLI'],
                required: true
            );

            $confirm = \Laravel\Prompts\confirm('¿Guardar cambios?', default: true);

            if ($confirm) {
                $data = [
                    'nombre_comercial' => $nombre,
                    'razon_social' => $nombre,
                    'nif_cif' => $nif,
                    'email' => $email,
                    'telefono' => $telefono,
                    'tipos' => $tiposSeleccionados
                ];

                $this->client->updateTercero($id, $data);
                \Laravel\Prompts\info('Tercero actualizado correctamente.');
                sleep(1);
            }
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error al actualizar: ' . $e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }

    private function renderTercerosList(array $terceros, int $page, int $lastPage, int $total, ?string $tipo, int $selectedIndex): void
    {
        $this->screen->clear();

        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $reset = "\033[0m";

        $tipoLabel = $tipo ? ucfirst($tipo) . 's' : 'Todos los Terceros';

        echo "{$cyan}╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                           {$tipoLabel}                                    ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝{$reset}\n\n";

        echo "{$white}Página {$yellow}{$page}{$white} de {$yellow}{$lastPage}{$white}  |  Total: {$yellow}{$total}{$white} terceros{$reset}\n\n";

        echo "{$cyan}";
        echo "  " . str_pad("Código", 12);
        echo str_pad("Nombre", 35);
        echo str_pad("NIF/CIF", 15);
        echo str_pad("Tipos", 20);
        echo "{$reset}\n";
        echo "{$cyan}  " . str_repeat("─", 80) . "{$reset}\n";

        if (empty($terceros)) {
            echo "\n  {$yellow}No hay terceros para mostrar{$reset}\n\n";
        } else {
            foreach ($terceros as $index => $tercero) {
                $prefix = ($index === $selectedIndex) ? "{$yellow}► " : "  ";
                $color = ($index === $selectedIndex) ? $yellow : $white;
                
                echo "{$prefix}{$color}";
                echo str_pad($tercero['codigo'] ?? '', 12);
                echo str_pad(substr($tercero['nombre_comercial'] ?? '', 0, 33), 35);
                echo str_pad($tercero['nif_cif'] ?? '', 15);
                
                $tipos = array_column($tercero['tipos'] ?? [], 'nombre');
                echo str_pad(implode(', ', $tipos), 20);
                echo "{$reset}\n";
            }
        }

        echo "\n{$cyan}  " . str_repeat("─", 80) . "{$reset}\n";
        echo "\n  {$white}↑/↓ Navegar  Enter Detalle  [N]uevo  [E]ditar  [P]ág.Ant.  [S]ig.  [ESC]Volver{$reset}\n";
    }

    /**
     * Listar solo clientes
     */
    public function listarClientes(): void
    {
        $this->listar('cliente');
    }

    /**
     * Listar solo proveedores
     */
    public function listarProveedores(): void
    {
        $this->listar('proveedor');
    }
}
