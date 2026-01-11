#!/usr/bin/env php
<?php

/**
 * ERP TUI Menu Panel
 * Panel de menú principal para navegación
 */

require __DIR__ . '/../vendor/autoload.php';

use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;
use App\NexErpTui\ErpClient;
use App\NexErpTui\Actions\TercerosActions;
use App\NexErpTui\Actions\DocumentosActions;
use App\NexErpTui\Actions\AlmacenActions;
use App\NexErpTui\Actions\DetailsActions;

// Optimización: Flush implícito para evitar buffering de salida y latencia visual
ob_implicit_flush(true);

// Obtener argumentos
$options = getopt('', ['auth-file:']);
$authFile = $options['auth-file'] ?? '';

$token = '';
$userName = 'Usuario';

if (file_exists($authFile)) {
    $authData = json_decode(file_get_contents($authFile), true);
    $token = $authData['token'] ?? '';
    $userName = $authData['user'] ?? 'Usuario';
}

// Inicializar componentes
$apiUrl = getenv('ERP_API_URL') ?: 'http://localhost:8000';
$client = new ErpClient($apiUrl);
$client->setToken($token);

$screen = new Screen([
    'bg' => "\033[40m",
    'fg_white' => "\033[37m",
    'fg_green' => "\033[32m",
    'fg_cyan' => "\033[36m",
    'fg_yellow' => "\033[33m",
    'reset' => "\033[0m",
]);

$keyHandler = new KeyHandler();
$keyHandler->setRawMode(true);

// Inicializar clases de acciones
$detailsActions = new DetailsActions($client, $screen, $keyHandler);
$tercerosActions = new TercerosActions($client, $screen, $keyHandler, $detailsActions);
$documentosActions = new DocumentosActions($client, $screen, $keyHandler, $detailsActions);
$almacenActions = new AlmacenActions($client, $screen, $keyHandler);

// Estructura de menú jerárquico
$menuStructure = [
    'ventas' => [
        'label' => 'Ventas',
        'key' => '1',
        'items' => [
            ['key' => '1', 'label' => 'Presupuestos', 'action' => 'presupuestos'],
            ['key' => '2', 'label' => 'Pedidos', 'action' => 'pedidos'],
            ['key' => '3', 'label' => 'Albaranes', 'action' => 'albaranes'],
            ['key' => '4', 'label' => 'Facturas', 'action' => 'facturas'],
            ['key' => '5', 'label' => 'Recibos', 'action' => 'recibos'],
        ]
    ],
    'terceros' => [
        'label' => 'Terceros',
        'key' => '2',
        'items' => [
            ['key' => '1', 'label' => 'Clientes', 'action' => 'clientes'],
            ['key' => '2', 'label' => 'Proveedores', 'action' => 'proveedores'],
            ['key' => '3', 'label' => 'Todos los Terceros', 'action' => 'terceros'],
            ['key' => '4', 'label' => 'Nuevo Tercero', 'action' => 'nuevo_tercero'],
        ]
    ],
    'almacen' => [
        'label' => 'Almacén',
        'key' => '3',
        'items' => [
            ['key' => '1', 'label' => 'Stock Actual', 'action' => 'stock'],
            ['key' => '2', 'label' => 'Productos (Lento)', 'action' => 'productos'],
        ]
    ],
];

$currentMenu = 'main';
$selectedIndex = 0;
$running = true;

function renderMainMenu($screen, $menuStructure, $selectedIndex, $userName) {
    $screen->clear();
    $green = Screen::GREEN; $cyan = Screen::CYAN; $yellow = Screen::YELLOW; 
    $white = Screen::WHITE; $reset = Screen::RESET; $bold = Screen::BOLD;
    
    echo "{$cyan}╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║               nexERP - MENÚ PRINCIPAL                         ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝{$reset}\n\n";
    
    echo "{$white}Usuario: {$green}{$userName}{$reset}\n\n";
    
    $index = 0;
    foreach ($menuStructure as $category) {
        $prefix = ($index === $selectedIndex) ? "{$yellow}► " : "  ";
        $color = ($index === $selectedIndex) ? $yellow : $white;
        echo "{$prefix}{$color}[{$category['key']}] {$category['label']}{$reset}\n";
        $index++;
    }
    
    echo "  {$white}[Q] Salir de la aplicación{$reset}\n\n";
    echo "{$cyan}═══════════════════════════════════════════════════════════════{$reset}\n\n";
    echo "  {$bold}{$white}TECLAS:{$reset}\n";
    echo "  {$yellow}↑ / ↓{$reset}   Navegar por categorías\n";
    echo "  {$yellow}Enter{$reset}   Entrar en la categoría seleccionada\n";
    echo "  {$yellow}Q / ESC{$reset} Salir de la aplicación\n";
}

function renderSubMenu($screen, $category, $selectedIndex, $userName) {
    $screen->clear();
    $green = Screen::GREEN; $cyan = Screen::CYAN; $yellow = Screen::YELLOW; 
    $white = Screen::WHITE; $reset = Screen::RESET;
    
    echo "{$cyan}╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║              {$category['label']}                                        ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝{$reset}\n\n";
    
    foreach ($category['items'] as $index => $item) {
        $prefix = ($index === $selectedIndex) ? "{$yellow}► " : "  ";
        $color = ($index === $selectedIndex) ? $yellow : $white;
        echo "{$prefix}{$color}[{$item['key']}] {$item['label']}{$reset}\n";
    }
    
    echo "\n  {$white}[ESC] Volver{$reset}\n\n";
    echo "{$cyan}═══════════════════════════════════════════════════════════════{$reset}\n";
}

try {
    $needsRender = true;
    while ($running) {
        if ($currentMenu === 'main') {
            if ($needsRender && !$keyHandler->hasPending()) {
                renderMainMenu($screen, $menuStructure, $selectedIndex, $userName);
                $needsRender = false;
            }
            $input = $keyHandler->waitForKey();
            if ($input === "") continue;
            
            switch ($input) {
                case "\e[A": 
                    if ($selectedIndex > 0) { $selectedIndex--; $needsRender = true; }
                    break;
                case "\e[B": 
                    if ($selectedIndex < count($menuStructure) - 1) { $selectedIndex++; $needsRender = true; }
                    break;
                case "\n": 
                    $categories = array_keys($menuStructure);
                    $currentMenu = $categories[$selectedIndex];
                    $selectedIndex = 0;
                    $needsRender = true;
                    break;
                case 'q': case 'Q': case "\e": $running = false; break;
            }
        } else {
            $category = $menuStructure[$currentMenu];
            if ($needsRender && !$keyHandler->hasPending()) {
                renderSubMenu($screen, $category, $selectedIndex, $userName);
                $needsRender = false;
            }
            $input = $keyHandler->waitForKey();
            if ($input === "") continue;
            
            switch ($input) {
                case "\e[A": 
                    if ($selectedIndex > 0) { $selectedIndex--; $needsRender = true; }
                    break;
                case "\e[B": 
                    if ($selectedIndex < count($category['items']) - 1) { $selectedIndex++; $needsRender = true; }
                    break;
                case "\e": case "\x1b": 
                    $currentMenu = 'main'; 
                    $selectedIndex = 0; 
                    $needsRender = true;
                    break;
                case "\n":
                    $action = $category['items'][$selectedIndex]['action'];
                    $keyHandler->setRawMode(false);
                    $keyHandler->clearStdin();
                    try {
                        switch ($action) {
                            case 'presupuestos': $documentosActions->listarPresupuestos(); break;
                            case 'pedidos': $documentosActions->listarPedidos(); break;
                            case 'albaranes': $documentosActions->listarAlbaranes(); break;
                            case 'facturas': $documentosActions->listarFacturas(); break;
                            case 'recibos': $documentosActions->listarRecibos(); break;
                            case 'clientes': $tercerosActions->listarClientes(); break;
                            case 'proveedores': $tercerosActions->listarProveedores(); break;
                            case 'terceros': $tercerosActions->listar(); break;
                            case 'nuevo_tercero': $tercerosActions->crear(); break;
                            case 'stock': $almacenActions->listarStock(); break;
                            case 'productos': $almacenActions->listarStock(); break;
                        }
                    } catch (\Exception $e) {
                        echo "\n❌ Error: " . $e->getMessage() . "\n";
                        sleep(2);
                    }
                    $keyHandler->setRawMode(true);
                    $needsRender = true;
                    break;
            }
        }
    }
} catch (\Exception $e) {
    echo "\nError fatal: " . $e->getMessage() . "\n";
} finally {
    $keyHandler->setRawMode(false);
    $screen->clear();
}
