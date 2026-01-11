#!/usr/bin/env php
<?php

/**
 * POS TUI Client - Terminal User Interface for Point of Sale
 * 
 * Interfaz de terminal de alto rendimiento para operaciones de caja
 * Estilo visual inspirado en Leroy Merlin (retro, monospaciado, alta densidad)
 * 
 * Requisitos:
 * - PHP 8.1+
 * - Composer dependencies (Guzzle, Laravel Prompts)
 * - Terminal compatible con ANSI escape codes
 * 
 * Uso:
 *   php bin/pos-tui.php
 *   
 * Variables de entorno:
 *   POS_API_URL - URL del backend API (default: http://localhost:8000)
 */

require __DIR__ . '/../vendor/autoload.php';

use App\PosTui\Display\Screen;
use App\PosTui\Input\KeyHandler;
use App\PosTui\PosClient;
use App\PosTui\TicketManager;

// Configuración
$config = [
    'api_url' => getenv('POS_API_URL') ?: 'http://localhost:8000',
    'colors' => [
        'bg' => "\033[40m",
        'fg_white' => "\033[37m",
        'fg_green' => "\033[32m",
        'fg_red' => "\033[31m",
        'fg_yellow' => "\033[33m",
        'reset' => "\033[0m",
    ],
];

// Inicializar componentes
$screen = new Screen($config['colors']);
$keyHandler = new KeyHandler();

try {
    // Pantalla de login
    $screen->clear();
    $screen->showLogo();
    
    echo "\n\n";
    echo "  ╔═══════════════════════════════════════╗\n";
    echo "  ║     SISTEMA POS - AUTENTICACIÓN       ║\n";
    echo "  ╚═══════════════════════════════════════╝\n\n";
    
    $keyHandler->setRawMode(false);
    
    $email = \Laravel\Prompts\text(
        label: 'Email del operador',
        placeholder: 'operador@ejemplo.com',
        required: true
    );
    
    $password = \Laravel\Prompts\password(
        label: 'Contraseña',
        required: true
    );
    
    // Autenticar
    $client = new PosClient($config['api_url']);
    $screen->showMessage('Autenticando...', 'info');
    
    $authData = $client->login($email, $password);
    $client->setToken($authData['token']);
    
    // Inicializar gestor de tickets
    $ticket = new TicketManager($client);
    
    // Limpiar pantalla y activar modo raw
    $keyHandler->setRawMode(true);
    $screen->clear();
    
    $running = true;
    $needsRefresh = true; // Flag para controlar cuándo refrescar
    $lastItemCount = 0;
    
    // Renderizado inicial
    $screen->render([
        'operator' => $authData['user']['name'],
        'session' => $ticket->getSessionId(),
        'items' => $ticket->getItems(),
        'totals' => $ticket->getTotals(),
    ]);
    
    // Bucle principal de eventos
    while ($running) {
        // Solo refrescar si hay cambios
        $currentItemCount = count($ticket->getItems());
        if ($needsRefresh || $currentItemCount !== $lastItemCount) {
            $screen->render([
                'operator' => $authData['user']['name'],
                'session' => $ticket->getSessionId(),
                'items' => $ticket->getItems(),
                'totals' => $ticket->getTotals(),
            ]);
            $needsRefresh = false;
            $lastItemCount = $currentItemCount;
        }
        
        // Leer input no bloqueante (100ms timeout para reducir CPU)
        $input = $keyHandler->read(100);
        
        if ($input === false) {
            continue;
            continue;
        }
        
        // Procesar teclas especiales
        switch ($input) {
            case "\e[15~": // F5 - Cobrar
                if ($ticket->isEmpty()) {
                    $screen->flashError('El ticket está vacío');
                    break;
                }
                
                $keyHandler->setRawMode(false);
                $screen->clear();
                
                echo "\n  ╔═══════════════════════════════════════╗\n";
                echo "  ║           PROCESAR PAGO               ║\n";
                echo "  ╚═══════════════════════════════════════╝\n\n";
                echo "  Total a pagar: " . number_format($ticket->getTotal(), 2, ',', '.') . " €\n\n";
                
                $paymentMethod = \Laravel\Prompts\select(
                    label: 'Método de pago',
                    options: [
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'mixed' => 'Mixto',
                    ],
                    default: 'cash'
                );
                
                $amountPaid = \Laravel\Prompts\text(
                    label: 'Cantidad recibida',
                    placeholder: number_format($ticket->getTotal(), 2, ',', '.'),
                    required: true,
                    validate: fn ($value) => is_numeric(str_replace(',', '.', $value)) 
                        ? null 
                        : 'Debe ser un número válido'
                );
                
                $amountPaid = (float) str_replace(',', '.', $amountPaid);
                
                try {
                    $result = $ticket->checkout($paymentMethod, $amountPaid);
                    
                    $screen->clear();
                    $screen->showReceipt($result);
                    
                    echo "\n\n  Presione ENTER para continuar...";
                    fgets(STDIN);
                    
                    $ticket->reset();
                    $needsRefresh = true; // Refrescar después de checkout
                } catch (\Exception $e) {
                    $screen->showMessage($e->getMessage(), 'error');
                    echo "\n  Presione ENTER para continuar...";
                    fgets(STDIN);
                }
                
                $keyHandler->setRawMode(true);
                break;
            
            case "\e[11~": // F1 - Buscar producto
                $keyHandler->setRawMode(false);
                $screen->clear();
                
                echo "\n  ╔═══════════════════════════════════════╗\n";
                echo "  ║         BUSCAR PRODUCTO               ║\n";
                echo "  ╚═══════════════════════════════════════╝\n\n";
                
                $query = \Laravel\Prompts\text(
                    label: 'Buscar por SKU o nombre',
                    placeholder: 'Escriba para buscar...',
                    required: true
                );
                
                try {
                    $product = $client->getProduct($query);
                    $ticket->addItem($product['id']);
                    $needsRefresh = true; // Refrescar después de añadir
                    $screen->showMessage("✓ Añadido: {$product['name']}", 'success');
                    sleep(1);
                } catch (\Exception $e) {
                    $screen->showMessage("✗ No encontrado: $query", 'error');
                    sleep(1);
                }
                
                $keyHandler->setRawMode(true);
                break;
            
            case "\e": // ESC - Cancelar última línea
                if (!$ticket->isEmpty()) {
                    $ticket->removeLastItem();
                    $screen->flashSuccess('Última línea eliminada');
                }
                break;
            
            case "\e[17~": // F6 - Totales del turno
                $keyHandler->setRawMode(false);
                $screen->clear();
                
                try {
                    $totals = $client->getTotals();
                    $screen->showTotals($totals);
                    
                    echo "\n\n  Presione ENTER para continuar...";
                    fgets(STDIN);
                } catch (\Exception $e) {
                    $screen->showMessage($e->getMessage(), 'error');
                    echo "\n  Presione ENTER para continuar...";
                    fgets(STDIN);
                }
                
                $keyHandler->setRawMode(true);
                break;
            
            case "\e[18~": // F7 - Salir
                $keyHandler->setRawMode(false);
                $confirm = \Laravel\Prompts\confirm(
                    label: '¿Seguro que desea salir?',
                    default: false
                );
                
                if ($confirm) {
                    $running = false;
                } else {
                    $keyHandler->setRawMode(true);
                }
                break;
            
            case "\n": // Enter - Procesar código de barras
                $barcode = $keyHandler->getBuffer();
                if (!empty($barcode)) {
                    try {
                        $product = $client->getProduct($barcode);
                        $ticket->addItem($product['id']);
                        $screen->flashSuccess("✓ {$product['name']}");
                    } catch (\Exception $e) {
                        $screen->flashError("✗ Producto no encontrado: $barcode");
                    }
                    $keyHandler->clearBuffer();
                }
                break;
            
            default:
                // Acumular en buffer (entrada de lector de códigos de barras)
                if (ctype_alnum($input) || in_array($input, ['-', '_'])) {
                    $keyHandler->appendBuffer($input);
                }
        }
    }
    
    // Limpieza al salir
    $keyHandler->setRawMode(false);
    $screen->clear();
    echo "\n  Sesión cerrada. ¡Hasta pronto!\n\n";
    
} catch (\Exception $e) {
    $keyHandler->setRawMode(false);
    $screen->clear();
    echo "\n\n  ╔═══════════════════════════════════════╗\n";
    echo "  ║              ERROR                    ║\n";
    echo "  ╚═══════════════════════════════════════╝\n\n";
    echo "  " . $e->getMessage() . "\n\n";
    exit(1);
}
