<?php

namespace App\PosTui\Input;

class KeyHandler
{
    private string $buffer = '';
    private $stdin;
    private bool $rawMode = false;

    public function __construct()
    {
        $this->stdin = fopen('php://stdin', 'r');
        stream_set_blocking($this->stdin, false);
        $this->setRawMode(true);
    }

    public function __destruct()
    {
        $this->setRawMode(false);
    }

    public function setRawMode(bool $enabled): void
    {
        if ($enabled && !$this->rawMode) {
            system('stty -icanon -echo');
            $this->rawMode = true;
        } elseif (!$enabled && $this->rawMode) {
            system('stty sane');
            $this->rawMode = false;
        }
    }

    public function read(int $timeoutMs = 100): string|false
    {
        $read = [$this->stdin];
        $write = $except = null;
        
        $sec = floor($timeoutMs / 1000);
        $usec = ($timeoutMs % 1000) * 1000;
        
        if (stream_select($read, $write, $except, $sec, $usec) > 0) {
            $char = fread($this->stdin, 16); // Leer hasta 16 bytes para secuencias de escape
            return $char !== false ? $char : false;
        }
        
        return false;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function appendBuffer(string $char): void
    {
        $this->buffer .= $char;
    }

    public function clearBuffer(): void
    {
        $this->buffer = '';
    }
}
