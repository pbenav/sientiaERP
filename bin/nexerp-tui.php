#!/usr/bin/env php
<?php

/**
 * ERP TUI Client - Versión Unificada
 * Cliente TUI para gestión de terceros y documentos de negocio
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

// Optimización: Flush implícito
ob_implicit_flush(true);

// Cargar variables de entorno
// Primero intentar cargar .tui.env (configuración específica del TUI)
$tuiEnvPath = __DIR__ . '/../.tui.env';
if (file_exists($tuiEnvPath)) {
    $dotenvTui = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.tui.env');
    $dotenvTui->safeLoad();
}

// Luego cargar .env principal (no sobrescribe variables ya definidas)
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Configuración
$config = [
    'api_url' => $_ENV['ERP_API_URL'] ?? getenv('ERP_API_URL') ?: ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8000'),
    'colors' => [
        'bg' => "\033[40m",
        'fg_white' => "\033[37m",
        'fg_green' => "\033[32m",
        'fg_blue' => "\033[34m",
        'fg_cyan' => "\033[36m",
        'fg_red' => "\033[31m",
        'fg_yellow' => "\033[33m",
        'reset' => "\033[0m",
    ],
];

// Inicializar componentes básicos
$screen = new Screen($config['colors']);
$keyHandler = new KeyHandler();

// MODO DESARROLLO: false para ver login y ASCII art
$devMode = false; 

try {
    $screen->clear();
    $token = '';
    $userName = 'Usuario';
    
    // -------------------------------------------------------------------------
    // 1. AUTENTICACIÓN
    // -------------------------------------------------------------------------
    
    if ($devMode) {
        $email = 'admin@sientia.com';
        $password = '12345678';
        echo "\n  \033[33m[MODO DESARROLLO ACTIVADO]\033[0m\n";
        echo "  Autologin como: {$email}\n";
        
        // Login automático en dev mode
        $client = new ErpClient($config['api_url']);
        $authData = $client->login($email, $password);
        if (isset($authData['token'])) {
            $token = $authData['token'];
            $userName = $authData['user']['name'] ?? 'Admin';
        }
    } else {
        // Pantalla de bienvenida con ASCII Art
        echo $config['colors']['fg_cyan'];
        echo "  ╔═══════════════════════════════════════════════════════════════════════════╗\n";
        echo "  ║                                                                           ║\n";
        echo "  ║    ███╗   ██╗███████╗██╗  ██╗███████╗██████╗ ██████╗                      ║\n";
        echo "  ║    ████╗  ██║██╔════╝╚██╗██╔╝██╔════╝██╔══██╗██╔══██╗                     ║\n";
        echo "  ║    ██╔██╗ ██║█████╗   ╚███╔╝ █████╗  ██████╔╝██████╔╝                     ║\n";
        echo "  ║    ██║╚██╗██║██╔══╝   ██╔██╗ ██╔══╝  ██╔══██╗██╔═══╝                      ║\n";
        echo "  ║    ██║ ╚████║███████╗██╔╝ ██╗███████╗██║  ██║██║                          ║\n";
        echo "  ║    ╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚═╝                          ║\n";
        echo "  ║                                                                           ║\n";
        echo "  ║                    Sistema de Gestión Empresarial                         ║\n";
        echo "  ║                                                                           ║\n";
        echo "  ╚═══════════════════════════════════════════════════════════════════════════╝\n";
        echo $config['colors']['reset'];
        
        echo "\n\n";
        echo "  ╔═══════════════════════════════════════╗\n";
        echo "  ║     SISTEMA nexERP - AUTENTICACIÓN    ║\n";
        echo "  ╚═══════════════════════════════════════╝\n\n";
        
        $keyHandler->setRawMode(false);
        
        $email = \Laravel\Prompts\text(
            label: 'Email del usuario',
            placeholder: 'usuario@ejemplo.com',
            required: true
        );
        
        $password = \Laravel\Prompts\password(
            label: 'Contraseña',
            required: true
        );
        
        // Autenticar
        $client = new ErpClient($config['api_url']);
        echo "\n  Autenticando en {$config['api_url']}...\n";
        
        $authData = $client->login($email, $password);
        
        if (!isset($authData['token'])) {
            throw new \Exception("Error: No se recibió un token de acceso.");
        }
        
        $token = $authData['token'];
        $userName = $authData['user']['name'] ?? 'Usuario';

        echo "  ✓ Autenticación exitosa\n";
        echo "  Iniciando interfaz...\n\n";
        sleep(1);
    }
    
    // Configurar cliente con token
    $client->setToken($token);
    
    // -------------------------------------------------------------------------
    // 2. INICIALIZACIÓN DE COMPONENTES DE MENÚ
    // -------------------------------------------------------------------------
    
    $screen->clear();
    
    // Actions
    $detailsActions = new DetailsActions($client, $screen, $keyHandler);
    $tercerosActions = new TercerosActions($client, $screen, $keyHandler, $detailsActions);
    $documentosActions = new DocumentosActions($client, $screen, $keyHandler, $detailsActions);
    $almacenActions = new AlmacenActions($client, $screen, $keyHandler);
    
    // Estructura de menú
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
            'label' => 'Compras',
            'items' => [
                ['label' => 'Pedidos', 'action' => 'compras_pedidos'],
                ['label' => 'Albaranes', 'action' => 'compras_albaranes'],
                ['label' => 'Facturas', 'action' => 'compras_facturas'],
                ['label' => 'Recibos', 'action' => 'compras_recibos'],
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
    
    // -------------------------------------------------------------------------
    // 3. EJECUCIÓN DEL MENÚ PRINCIPAL
    // -------------------------------------------------------------------------
    
    // Crear objeto MainMenu
    $menu = new MainMenu($keyHandler, $screen, $menuStructure, \App\NexErpTui\Display\ColorTheme::IBM_GREEN);
    
    $running = true;
    while ($running) {
        $result = $menu->run($userName);
        
        if (!$result) {
            continue;
        }
        
        $action = $result['action'];
        
        if ($action === 'exit') {
            $running = false;
            break;
        }
        
        // Ejecutar acción del sistema
        $keyHandler->setRawMode(false);
        $keyHandler->clearStdin();
        
        try {
            switch ($action) {
                // Compras
                case 'compras_pedidos':
                    $documentosActions->listarPedidosCompra();
                    break;
                case 'compras_albaranes':
                    $documentosActions->listarAlbaranesCompra();
                    break;
                case 'compras_facturas':
                    $documentosActions->listarFacturasCompra();
                    break;
                case 'compras_recibos':
                    $documentosActions->listarRecibosCompra();
                    break;

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
    if (isset($keyHandler)) {
        $keyHandler->setRawMode(false);
    }
    if (isset($screen)) {
        $screen->clear();
    }
    
    echo "\n\n  ╔═══════════════════════════════════════╗\n";
    echo "  ║              ERROR                    ║\n";
    echo "  ╚═══════════════════════════════════════╝\n\n";
    echo "  " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    exit(1);
} finally {
    if (isset($keyHandler)) {
        $keyHandler->setRawMode(false);
    }
    if (isset($screen)) {
        $screen->clear();
    }
    echo "\033[1;32m¡Hasta pronto!\033[0m\n\n";
}
