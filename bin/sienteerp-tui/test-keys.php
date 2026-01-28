<?php

/**
 * Script de prueba para detectar qué tecla envía el terminal para BACKSPACE
 */

echo "=== TEST DE TECLAS ===\n";
echo "Presiona BACKSPACE y luego Enter para ver qué código envía tu terminal\n";
echo "Presiona 'q' y Enter para salir\n\n";

// Modo raw
system('stty -icanon -echo min 1 time 0');

$stdin = fopen('php://stdin', 'r');
stream_set_read_buffer($stdin, 0);

while (true) {
    echo "Presiona una tecla: ";
    
    $char = fread($stdin, 1);
    
    if ($char === 'q') {
        break;
    }
    
    // Mostrar información de la tecla
    echo "\n";
    echo "  Carácter: " . ($char === "\n" ? "\\n" : ($char === "\t" ? "\\t" : $char)) . "\n";
    echo "  Código decimal: " . ord($char) . "\n";
    echo "  Código hexadecimal: 0x" . dechex(ord($char)) . "\n";
    echo "  Código octal: \\" . decoct(ord($char)) . "\n";
    echo "  Representación PHP: ";
    
    if (ord($char) < 32) {
        echo "\\x" . str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT) . "\n";
    } else {
        echo "'" . $char . "'\n";
    }
    
    // Si es ESC, leer secuencia completa
    if ($char === "\e" || $char === "\x1b") {
        echo "  ¡Es una secuencia ESC! Leyendo resto...\n";
        
        $read = [$stdin];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, 0, 50000) > 0) {
            $more = fread($stdin, 10);
            echo "  Secuencia completa: ESC + '" . $more . "'\n";
            echo "  Códigos: ";
            for ($i = 0; $i < strlen($more); $i++) {
                echo ord($more[$i]) . " ";
            }
            echo "\n";
        }
    }
    
    echo "\n";
}

// Restaurar terminal
system('stty sane');
fclose($stdin);

echo "\n¡Adiós!\n";
