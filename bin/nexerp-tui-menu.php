#!/usr/bin/env php
<?php

/**
 * ERP TUI Menu Panel - Versión 2.0 con FullScreenLayout
 * Sistema completo tipo IBM 5250/3270 con paleta de 7 colores
 */

require __DIR__ . '/../vendor/autoload.php';

use App\NexErpTui\Display\Screen;
use App\NexErpTui\Display\MainMenu;
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

// Inicializar clases de acciones
$detailsActions = new DetailsActions($client, $screen, $keyHandler);
$tercerosActions = new TercerosActions($client, $screen, $keyHandler, $detailsActions);
$documentosActions = new DocumentosActions($client, $screen, $keyHandler, $detailsActions);
$almacenActions = new AlmacenActions($client, $screen, $keyHandler);

// Estructura de menú jerárquico
$menuStructure = [
    [
        'label' => 'Ventas',
        'items' => [
            ['label' => 'Presupuestos', 'action' => 'presupuestos'],
            ['label' => 'Pedidos', 'action' => 'pedidos'],
            ['label' => 'Albaranes', 'action' => 'albaranes'],
            ['label' => 'Facturas', 'action' => 'facturas'],
            ['label' => 'Recibos', 'action' => 'recibos'],
        ]
    ],
    [
        'label' => 'Terceros',
        'items' => [
            ['label' => 'Clientes', 'action' => 'clientes'],
            ['label' => 'Proveedores', 'action' => 'proveedores'],
            ['label' => 'Todos los Terceros', 'action' => 'terceros'],
            ['label' => 'Nuevo Tercero', 'action' => 'nuevo_tercero'],
        ]
    ],
    [
        'label' => 'Almacén',
        'items' => [
            ['label' => 'Stock Actual', 'action' => 'stock'],
            ['label' => 'Productos', 'action' => 'productos'],
        ]
    ],
];

try {
    // Crear menú principal con tema IBM Green
    $menu = new MainMenu($keyHandler, $screen, $menuStructure, \App\NexErpTui\Display\ColorTheme::IBM_GREEN);
    
    $running = true;
    while ($running) {
        $result = $menu->run($userName);
        
        if (!$result) {
            continue;
        }
        
        $action = $result['action'];
        
        // Salir
        if ($action === 'exit') {
            $running = false;
            break;
        }
        
        // Ejecutar acción
        $keyHandler->setRawMode(false);
        $keyHandler->clearStdin();
        
        try {
            switch ($action) {
                // Ventas
                case 'presupuestos':
                    $documentosActions->listarPresupuestos();
                    break;
                case 'pedidos':
                    $documentosActions->listarPedidos();
                    break;
                case 'albaranes':
                    $documentosActions->listarAlbaranes();
                    break;
                case 'facturas':
                    $documentosActions->listarFacturas();
                    break;
                case 'recibos':
                    $documentosActions->listarRecibos();
                    break;
                
                // Terceros
                case 'clientes':
                    $tercerosActions->listarClientes();
                    break;
                case 'proveedores':
                    $tercerosActions->listarProveedores();
                    break;
                case 'terceros':
                    $tercerosActions->listar();
                    break;
                case 'nuevo_tercero':
                    $tercerosActions->crear();
                    break;
                
                // Almacén
                case 'stock':
                case 'productos':
                    $almacenActions->listarStock();
                    break;
            }
        } catch (\Exception $e) {
            echo "\n\033[1;31m❌ Error: " . $e->getMessage() . "\033[0m\n";
            echo "\nPresione cualquier tecla para continuar...";
            $keyHandler->setRawMode(true);
            $keyHandler->waitForKey();
        }
        
        $keyHandler->setRawMode(true);
    }
} catch (\Throwable $e) {
    $msg = "Error fatal: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine();
    file_put_contents('/tmp/tui_error.log', $msg . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    echo "\n\033[1;31m" . $msg . "\033[0m\n";
    // echo $e->getTraceAsString() . "\n";
    sleep(5); // Dar tiempo a leer
} finally {
    $keyHandler->setRawMode(false);
    $screen->clear();
    echo "\033[1;32m¡Hasta pronto!\033[0m\n\n";
    file_put_contents('/tmp/tui_debug.log', "Fin script\n", FILE_APPEND);
}
