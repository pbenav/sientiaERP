#!/usr/bin/env php
<?php
// diag-input.php - Herramienta de diagnóstico de teclado

echo "Modo diagnóstico. Pulse teclas para ver sus códigos.\n";
echo "Pulse 'Q' para salir.\n\n";

$original = trim(shell_exec('stty -g'));
shell_exec('stty -icanon -echo -isig min 0 time 1');

$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);

$running = true;
while ($running) {
    $read = [$stdin];
    $write = $except = null;
    if (stream_select($read, $write, $except, 0, 100000) > 0) {
        $data = fread($stdin, 16);
        if ($data !== "") {
            $hex = bin2hex($data);
            echo "Tecla detectada: [" . $data . "] | Hex: 0x" . $hex . " | Len: " . strlen($data) . "\n";
            if (strtolower($data) === 'q') $running = false;
        }
    }
}

shell_exec('stty ' . escapeshellarg($original));
echo "\nDiagnóstico finalizado.\n";
