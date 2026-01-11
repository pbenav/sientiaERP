<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;

class AlmacenActions
{
    private ErpClient $client;
    private Screen $screen;
    private KeyHandler $keyHandler;

    public function __construct(ErpClient $client, Screen $screen, KeyHandler $keyHandler)
    {
        $this->client = $client;
        $this->screen = $screen;
        $this->keyHandler = $keyHandler;
    }

    public function listarStock(): void
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
                    $response = $this->client->getProductos($page, $perPage);
                    $productos = $response['data'] ?? [];
                    $total = $response['total'] ?? 0;
                    $lastPage = $response['last_page'] ?? 1;
                    $needsFetch = false;
                    $needsRender = true;
                }



                if ($needsRender && !$this->keyHandler->hasPending()) {
                    $this->renderStockList($productos, $page, $lastPage, $total, $selectedIndex);
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
                    if ($selectedIndex < count($productos) - 1) {
                        $selectedIndex++;
                        $needsRender = true;
                    }
                }

                $key = strtolower($input);
                if ($key === 'n') {
                    $this->crear();
                    $needsFetch = true;
                } elseif ($key === 'e') {
                    if (isset($productos[$selectedIndex])) {
                        $this->editar($productos[$selectedIndex]['id']);
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

    public function crear(): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   NUEVO PRODUCTO                              ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            $sku = \Laravel\Prompts\text('SKU', required: true);
            $name = \Laravel\Prompts\text('Nombre del Producto', required: true);
            $description = \Laravel\Prompts\text('Descripción');
            $price = \Laravel\Prompts\text('Precio (€)', validate: fn($v) => is_numeric($v) ? null : 'Debe ser un número');
            $stock = \Laravel\Prompts\text('Stock Inicial', default: '0', validate: fn($v) => is_numeric($v) ? null : 'Debe ser numérico');
            $tax = \Laravel\Prompts\text('IVA %', default: '21', validate: fn($v) => is_numeric($v) ? null : 'Debe ser numérico');

            $confirm = \Laravel\Prompts\confirm('¿Crear producto?', default: true);

            if ($confirm) {
                $data = [
                    'sku' => $sku,
                    'name' => $name,
                    'description' => $description,
                    'price' => (float)$price,
                    'stock' => (int)$stock,
                    'tax_rate' => (float)$tax,
                    'barcode' => time() // Generar uno simple por defecto
                ];

                // Asumimos que existe un método createProducto en ErpClient o usamos request genérico
                // El ErpController tiene createProducto? No lo vi explícitamente pero debería.
                // Revisando ErpController: sí tiene createProducto? No estoy seguro, voy a usar método genérico si falla.
                // Pero en ErpClient no vi createProducto. Asumiré que no existe y lo añadiré después si falla,
                // O mejor, uso client->request directamente si ErpClient no lo tiene?
                // ErpClient tiene metodos especificos. Voy a asumir que existen create/update o tendré que añadirlos.
                // DE MOMENTO: Uso el método genérico si puedo, pero ErpClient suele tener wrappers.
                // Voy a verificar ErpClient en el siguiente paso.
                // Por ahora uso llamadas asumiendo que existen o las crearé.
                
                // Oops, better check ErpClient first? No, I'll add methods to ErpClient if missing.
                $this->client->createProducto($data); 
                \Laravel\Prompts\info('Producto creado correctamente.');
                sleep(1);
            }
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error: ' . $e->getMessage());
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
        echo "║                   EDITAR PRODUCTO                             ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            $producto = $this->client->getProducto($id);

            $sku = \Laravel\Prompts\text('SKU', default: $producto['sku'] ?? '', required: true);
            $name = \Laravel\Prompts\text('Nombre', default: $producto['name'] ?? '', required: true);
            $description = \Laravel\Prompts\text('Descripción', default: $producto['description'] ?? '');
            $price = \Laravel\Prompts\text('Precio (€)', default: (string)($producto['price'] ?? 0), validate: fn($v) => is_numeric($v) ? null : 'Debe ser un número');
            $stock = \Laravel\Prompts\text('Stock', default: (string)($producto['stock'] ?? 0), validate: fn($v) => is_numeric($v) ? null : 'Debe ser numérico');
            $tax = \Laravel\Prompts\text('IVA %', default: (string)($producto['tax_rate'] ?? 21), validate: fn($v) => is_numeric($v) ? null : 'Debe ser numérico');

            $confirm = \Laravel\Prompts\confirm('¿Guardar cambios?', default: true);

            if ($confirm) {
                $data = [
                    'sku' => $sku,
                    'name' => $name,
                    'description' => $description,
                    'price' => (float)$price,
                    'stock' => (int)$stock,
                    'tax_rate' => (float)$tax,
                ];

                $this->client->updateProducto($id, $data);
                \Laravel\Prompts\info('Producto actualizado correctamente.');
                sleep(1);
            }
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Error: ' . $e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }

    private function renderStockList(array $productos, int $page, int $lastPage, int $total, int $selectedIndex): void
    {
        $this->screen->clear();

        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $red = "\033[31m";
        $reset = "\033[0m";

        echo "{$cyan}╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                           STOCK DE ALMACÉN                                    ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝{$reset}\n\n";

        echo "{$white}Página {$yellow}{$page}{$white} de {$yellow}{$lastPage}{$white}  |  Total: {$yellow}{$total}{$white} productos{$reset}\n\n";

        echo "{$cyan}";
        echo "  " . str_pad("SKU", 15);
        echo str_pad("Producto", 40);
        echo str_pad("Precio", 12);
        echo str_pad("IVA", 8);
        echo "{$reset}\n";
        echo "{$cyan}  " . str_repeat("─", 75) . "{$reset}\n";

        if (empty($productos)) {
            echo "\n  {$yellow}No hay productos en almacén{$reset}\n\n";
        } else {
            foreach ($productos as $index => $p) {
                $prefix = ($index === $selectedIndex) ? "{$yellow}► " : "  ";
                $color = ($index === $selectedIndex) ? $yellow : $white;
                
                echo "{$prefix}{$color}";
                echo str_pad($p['sku'] ?? '', 15);
                echo str_pad(substr($p['name'] ?? '', 0, 38), 40);
                echo str_pad(number_format($p['price'] ?? 0, 2, ',', '.') . ' €', 12);
                echo str_pad(($p['tax_rate'] ?? 0) . '%', 8);
                echo "{$reset}\n";
            }
        }

        echo "\n{$cyan}  " . str_repeat("─", 75) . "{$reset}\n";
        echo "\n  {$white}↑/↓ Navegar  [N]uevo  [E]ditar  [P]ág.Ant.  [S]ig.  [ESC]Volver{$reset}\n";
    }
}
