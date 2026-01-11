#!/usr/bin/env php
<?php

/**
 * ERP TUI Client - Terminal User Interface for ERP Management
 * 
 * Cliente TUI para gestión de terceros y documentos de negocio
 * Usa tmux para crear zonas separadas en la interfaz
 * 
 * Requisitos:
 * - PHP 8.2+
 * - tmux instalado
 * - Composer dependencies
 * 
 * Uso:
 *   php bin/erp-tui.php
 *   
 * Variables de entorno:
 *   ERP_API_URL - URL del backend API (default: http://localhost:8000)
 */

require __DIR__ . '/../vendor/autoload.php';

use App\NexErpTui\TmuxManager;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;
use App\PosTui\PosClient;

// Verificar que tmux está instalado
if (!shell_exec('which tmux')) {
    echo "\n❌ Error: tmux no está instalado.\n";
    echo "Instálalo con: sudo apt install tmux\n\n";
    exit(1);
}

// Configuración
$config = [
    'api_url' => getenv('ERP_API_URL') ?: 'http://localhost:8000',
    'session_name' => 'nexerp-tui',
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

// Inicializar componentes
$screen = new Screen($config['colors']);
$keyHandler = new KeyHandler();

// MODO DESARROLLO: Cambiar a true para saltar el login manualmente
// ¡IMPORTANTE: Cambiar a false antes de pasar a producción!
$devMode = true; 

try {
    // ... Pantalla de carga anterior ...
    $screen->clear();
    // (Mantenemos el logo por estética si se desea, o lo saltamos)
    
    if ($devMode) {
        $email = 'admin@sientia.com';
        $password = '12345678';
        echo "\n  \033[33m[MODO DESARROLLO ACTIVADO]\033[0m\n";
        echo "  Autologin como: {$email}\n";
    } else {
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
    }
    
    // Autenticar
    $client = new \App\NexErpTui\ErpClient($config['api_url']);
    echo "\n  Autenticando en {$config['api_url']}...\n";
    
    $authData = $client->login($email, $password);
    
    if (!isset($authData['token'])) {
        throw new \Exception("Error: No se recibió un token de acceso.");
    }

    echo "  ✓ Autenticación exitosa\n";
    echo "  Iniciando interfaz...\n\n";
    
    sleep(1);
    
    // Guardar token en archivo temporal
    $authFile = '/tmp/nexerp_tui_auth.json';
    file_put_contents($authFile, json_encode([
        'token' => $authData['token'],
        'user' => $authData['user']['name'] ?? 'Admin'
    ]));
    chmod($authFile, 0600);

    // Ejecutar el menú directamente
    $screen->clear();
    $authFileArg = escapeshellarg($authFile);
    passthru("php bin/nexerp-tui-menu.php --auth-file={$authFileArg}");
    
} catch (\Exception $e) {
    $keyHandler->setRawMode(false);
    $screen->clear();
    echo "\n\n  ╔═══════════════════════════════════════╗\n";
    echo "  ║              ERROR                    ║\n";
    echo "  ╚═══════════════════════════════════════╝\n\n";
    echo "  " . $e->getMessage() . "\n\n";
    exit(1);
}
