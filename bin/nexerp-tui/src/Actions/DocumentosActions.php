<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;

class DocumentosActions
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
     * Listar documentos por tipo
     */
    public function listar(string $tipo): void
    {
        $page = 1;
        $perPage = 15;
        $running = true;
        $selectedIndex = 0;
        $selectionFile = '/tmp/erp_tui_selection.json';

        $tipoLabels = [
            'presupuesto' => 'Presupuestos',
            'pedido' => 'Pedidos',
            'albaran' => 'Albaranes',
            'factura' => 'Facturas',
            'recibo' => 'Recibos',
        ];

        $needsFetch = true;
        $needsRender = true;

        while ($running) {
            try {
                if ($needsFetch) {
                    $response = $this->client->getDocumentos($tipo, $page, $perPage);
                    $documentos = $response['data'] ?? [];
                    $total = $response['total'] ?? 0;
                    $lastPage = $response['last_page'] ?? 1;
                    $needsFetch = false;
                    $needsRender = true;
                }



                if ($needsRender && !$this->keyHandler->hasPending()) {
                    $this->renderDocumentosList($documentos, $page, $lastPage, $total, $tipoLabels[$tipo] ?? $tipo, $selectedIndex);
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
                    if ($selectedIndex < count($documentos) - 1) {
                        $selectedIndex++;
                        $needsRender = true;
                    }
                } elseif ($input === "\n") { // Enter - Select
                    if (isset($documentos[$selectedIndex])) {
                        $this->details->renderDocumento($documentos[$selectedIndex]['id']);
                        $needsRender = true;
                    }
                }

                $key = strtolower($input);
                if ($key === 'n') {
                    $this->crear($tipo);
                    $needsFetch = true;
                } elseif ($key === 'e') {
                    if (isset($documentos[$selectedIndex])) {
                        $this->editar($documentos[$selectedIndex]['id']);
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

    public function editar(int $id): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   EDITAR DOCUMENTO                            ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            $doc = $this->client->getDocumento($id);
            
            // Editar Estado
            $estados = ['borrador', 'confirmado', 'anulado', 'completado'];
            $estado = \Laravel\Prompts\select(
                'Estado', 
                options: array_combine($estados, $estados),
                default: $doc['estado'] ?? 'borrador'
            );

            // Editar Fecha
            $fecha = \Laravel\Prompts\text(
                'Fecha (YYYY-MM-DD)', 
                default: $doc['fecha'] ?? date('Y-m-d'),
                validate: fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? null : 'Formato inválido'
            );

            // Cambiar cliente? (Opcional, puede ser complejo si afecta precios/tarifas)
            // Lo permitimos simple.
            $cambiarCliente = \Laravel\Prompts\confirm('¿Cambiar cliente?', default: false);
            $clienteId = $doc['tercero_id'];

            if ($cambiarCliente) {
                $tercerosResponse = $this->client->getTerceros(perPage: 100, tipo: 'cliente');
                $clientesOptions = [];
                foreach ($tercerosResponse['data'] ?? [] as $t) {
                    $clientesOptions[$t['id']] = $t['nombre_comercial'];
                }
                if (!empty($clientesOptions)) {
                    $clienteId = \Laravel\Prompts\select('Nuevo Cliente', options: $clientesOptions, default: $clienteId);
                }
            }

            $confirm = \Laravel\Prompts\confirm('¿Guardar cambios?', default: true);

            if ($confirm) {
                $data = [
                    'estado' => $estado,
                    'fecha' => $fecha,
                    'tercero_id' => $clienteId
                ];

                $this->client->updateDocumento($id, $data);
                \Laravel\Prompts\info('Documento actualizado.');
                sleep(1);
            }

        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error: ' . $e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }

    public function crear(string $tipo): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        $tipoLabel = match($tipo) {
            'presupuesto' => 'PRESUPUESTO',
            'pedido' => 'PEDIDO',
            default => strtoupper($tipo),
        };

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   NUEVO $tipoLabel                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            // 1. Seleccionar Cliente
            $tercerosResponse = $this->client->getTerceros(perPage: 100, tipo: 'cliente');
            $clientes = [];
            foreach ($tercerosResponse['data'] ?? [] as $t) {
                $clientes[$t['id']] = $t['nombre_comercial'] . " (" . $t['nif_cif'] . ")";
            }

            if (empty($clientes)) {
                \Laravel\Prompts\error('No hay clientes registrados.');
                sleep(2);
                $this->keyHandler->setRawMode(true);
                return;
            }

            $clienteId = \Laravel\Prompts\select('Seleccione Cliente', options: $clientes, required: true);

            // 2. Líneas
            $lineas = [];
            $añadirCosas = true;

            while ($añadirCosas) {
                $screen_tmp = new Screen([]);
                $screen_tmp->clear();
                echo "\033[36mLÍNEAS ACTUALES: " . count($lineas) . "\033[0m\n\n";

                $buscar = \Laravel\Prompts\text('Buscar producto (ESC para terminar)', placeholder: 'Nombre o SKU...');
                
                if (empty($buscar)) {
                    $añadirCosas = false;
                    continue;
                }

                $productosResponse = $this->client->searchProducto($buscar);
                $productos = [];
                foreach ($productosResponse as $p) {
                    $productos[$p['id']] = $p['name'] . " - " . number_format($p['price'], 2) . "€";
                }

                if (empty($productos)) {
                    \Laravel\Prompts\warning('No se encontraron productos.');
                    continue;
                }

                $productoId = \Laravel\Prompts\select('Seleccione Producto', options: $productos);
                $cantidad = \Laravel\Prompts\text('Cantidad', default: '1', validate: fn($v) => is_numeric($v) ? null : 'Debe ser un número');
                
                $p = null;
                foreach ($productosResponse as $prod) {
                    if ($prod['id'] == $productoId) { $p = $prod; break; }
                }

                $lineas[] = [
                    'product_id' => $p['id'],
                    'codigo' => $p['sku'],
                    'descripcion' => $p['name'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $p['price'],
                    'iva' => $p['tax_rate'],
                ];

                $añadirCosas = \Laravel\Prompts\confirm('¿Añadir más productos?', default: true);
            }

            if (empty($lineas)) {
                \Laravel\Prompts\warning('Documento cancelado (sin líneas).');
                sleep(1);
            } else {
                $confirm = \Laravel\Prompts\confirm('¿Desea crear el documento?', default: true);

                if ($confirm) {
                    $data = [
                        'tipo' => $tipo,
                        'tercero_id' => $clienteId,
                        'fecha' => date('Y-m-d'),
                        'lineas' => $lineas
                    ];

                    $this->client->createDocumento($data);
                    \Laravel\Prompts\info('Documento creado correctamente.');
                    sleep(1);
                }
            }
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error al crear documento: ' . $e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }

    private function renderDocumentosList(array $documentos, int $page, int $lastPage, int $total, string $tipoLabel, int $selectedIndex): void
    {
        $this->screen->clear();

        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $red = "\033[31m";
        $reset = "\033[0m";

        echo "{$cyan}╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                           {$tipoLabel}                                    ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝{$reset}\n\n";

        echo "{$white}Página {$yellow}{$page}{$white} de {$yellow}{$lastPage}{$white}  |  Total: {$yellow}{$total}{$white} documentos{$reset}\n\n";

        echo "{$cyan}";
        echo "  " . str_pad("Número", 18);
        echo str_pad("Fecha", 12);
        echo str_pad("Cliente", 30);
        echo str_pad("Total", 12);
        echo str_pad("Estado", 10);
        echo "{$reset}\n";
        echo "{$cyan}  " . str_repeat("─", 80) . "{$reset}\n";

        if (empty($documentos)) {
            echo "\n  {$yellow}No hay documentos para mostrar{$reset}\n\n";
        } else {
            foreach ($documentos as $index => $doc) {
                $prefix = ($index === $selectedIndex) ? "{$yellow}► " : "  ";
                $color = ($index === $selectedIndex) ? $yellow : $white;
                
                $estadoColor = match($doc['estado'] ?? 'borrador') {
                    'confirmado', 'cobrado', 'pagado' => $green,
                    'anulado' => $red,
                    default => $yellow,
                };

                echo "{$prefix}{$color}";
                echo str_pad($doc['numero'] ?? '', 18);
                echo str_pad($doc['fecha'] ?? '', 12);
                echo str_pad(substr($doc['tercero']['nombre_comercial'] ?? '', 0, 28), 30);
                echo str_pad(number_format($doc['total'] ?? 0, 2, ',', '.') . ' €', 12);
                echo "{$estadoColor}" . str_pad(ucfirst($doc['estado'] ?? ''), 10) . "{$reset}";
                echo "\n";
            }
        }

        echo "\n{$cyan}  " . str_repeat("─", 80) . "{$reset}\n";
        echo "\n  {$white}↑/↓ Navegar  Enter Detalle  [N]uevo  [E]ditar  [P]ág.Ant.  [S]ig.  [ESC]Volver{$reset}\n";
    }

    /**
     * Métodos específicos por tipo de documento
     */
    public function listarPresupuestos(): void
    {
        $this->listar('presupuesto');
    }

    public function listarPedidos(): void
    {
        $this->listar('pedido');
    }

    public function listarAlbaranes(): void
    {
        $this->listar('albaran');
    }

    public function listarFacturas(): void
    {
        $this->listar('factura');
    }

    public function listarRecibos(): void
    {
        $this->listar('recibo');
    }
}
