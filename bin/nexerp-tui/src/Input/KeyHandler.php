<?php

namespace App\NexErpTui\Input;

class KeyHandler
{
    private $originalSttyMode;
    private bool $rawMode = false;
    private $stdin;

    public function __construct()
    {
        $this->originalSttyMode = trim(shell_exec('stty -g'));
        $this->stdin = fopen('php://stdin', 'r');
        stream_set_read_buffer($this->stdin, 0); // Desactivar buffer interno de PHP
    }

    public function __destruct()
    {
        if ($this->rawMode) {
            $this->setRawMode(false);
        }
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
    }

    public function setRawMode(bool $enabled): void
    {
        if ($enabled) {
            // Modo "casi raw": bloqueante, sin echo, pero mantenemos post-procesado (opost) para \n
            system('stty -icanon -echo -isig min 1 time 0');
            $this->rawMode = true;
        } else {
            // Restaurar modo original
            if ($this->originalSttyMode) {
                system('stty ' . escapeshellarg($this->originalSttyMode));
            } else {
                system('stty sane');
            }
            $this->rawMode = false;
        }
    }

    public function clearStdin(): void
    {
        // Vaciar buffer usando stream_select para no bloquear
        $read = [$this->stdin];
        $write = null;
        $except = null;
        
        while (stream_select($read, $write, $except, 0, 0) > 0) {
            fread($this->stdin, 4096);
            $read = [$this->stdin];
        }
    }

    /**
     * Espera una tecla (bloqueante). Devuelve la secuencia completa.
     */
    private string $buffer = '';

    /**
     * Comprueba si hay teclas pendientes en el buffer o en el stream.
     */
    public function hasPending(): bool
    {
        if ($this->buffer !== '') {
            return true;
        }

        // Peek sin bloquear
        $read = [$this->stdin];
        $write = null;
        $except = null;
        return stream_select($read, $write, $except, 0, 0) > 0;
    }

    /**
     * Espera una tecla (bloqueante). Devuelve la secuencia completa.
     */
    public function waitForKey(): string
    {
        // 1. Si el buffer tiene datos, intenta extraer una tecla
        if ($this->buffer !== '') {
            return $this->extractKey();
        }

        // 2. Leer del stream (bloqueante el primer byte)
        $char = fread($this->stdin, 1);
        
        if ($char === false || $char === "") {
            return ""; // EOF o Error
        }
        
        $this->buffer .= $char;

        // 3. Si es ESC, mirar si hay bytes pendientes inmediatamente (secuencia rápida)
        if ($char === "\e" || $char === "\x1b") {
            $read = [$this->stdin];
            $write = null;
            $except = null;
            
            // Esperar brevemente (5ms) para capturar el resto de la secuencia si existe
            // 50ms era demasiado conservador y causaba lag en redes rápidas/locales
            if (stream_select($read, $write, $except, 0, 5000) > 0) {
                // Leemos todo lo que haya (hasta 1024 bytes) para llenar el buffer
                $more = fread($this->stdin, 1024); 
                if ($more !== false) {
                    $this->buffer .= $more;
                }
            }
        }
        
        // Ensure signals (like SIGWINCH) are processed
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // 4. Procesar buffer
        return $this->extractKey();
    }
    private function extractKey(): string
    {
        if ($this->buffer === '') return '';

        $c = $this->buffer[0];

        // Caso 1: Carácter normal (no ESC)
        if ($c !== "\e" && $c !== "\x1b") {
            // Extraer primer carácter
            $key = $c;
            $this->buffer = substr($this->buffer, 1);
            return $key;
        }

        // Caso 2: Es ESC. Ver si forma una secuencia conocida.
        // Si el buffer es solo ESC, lo devolvemos tal cual (tras haber esperado en el paso 3)
        if (strlen($this->buffer) === 1) {
            $this->buffer = '';
            return $c;
        }

        // Buscar secuencias ANSI (CSI): \e [ ... letra
        // Regex: \e seguido de [ , luego params opcionales (digitos, ;, ?), luego letra final (A-Z, ~)
        if (preg_match('/^(\e|\\x1b)\[[0-9;?]*[A-Za-z~]/', $this->buffer, $matches)) {
            $seq = $matches[0];
            $this->buffer = substr($this->buffer, strlen($seq));
            return $seq;
        }
        
        // Buscar secuencias SS3 (O): \e O letra (ej: F1-F4 en algunos term)
        if (preg_match('/^(\e|\\x1b)O[A-Za-z]/', $this->buffer, $matches)) {
            $seq = $matches[0];
            $this->buffer = substr($this->buffer, strlen($seq));
            return $seq;
        }

        // Si tenemos ESC + algo que no encaja en patrones conocidos,
        // asumimos que son pulsaciones separadas (Alt+Key o ESC seguido de tecla).
        // Devolvemos solo ESC y dejamos el resto para la siguiente llamada.
        $this->buffer = substr($this->buffer, 1);
        return $c;
    }
}
