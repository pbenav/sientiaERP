<?php

namespace App\SienteErpTui\Display;

class Screen
{
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";
    public const BOLD = "\033[1m";
    public const RESET = "\033[0m";

    private ColorTheme $theme;

    public function __construct(?ColorTheme $theme = null)
    {
        $this->theme = $theme ?? new ColorTheme('vibrant');
    }

    public function getTheme(): ColorTheme
    {
        return $this->theme;
    }

    public function color(string $key): string
    {
        return $this->theme->get($key);
    }

    public function reset(): string
    {
        return $this->theme->get('reset');
    }

    public function clear(): void
    {
        $bg = $this->theme->get('main_bg');
        // Limpiar pantalla (\033[2J) y mover cursor a 1,1 (\033[H)
        echo "{$bg}\033[2J\033[H";
    }

    public function render(string $content): void
    {
        echo $content;
    }

    public function showMessage(string $message, string $type = 'info'): void
    {
        $color = $this->color($type);
        $reset = $this->reset();
        
        $icon = match($type) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            default => 'ℹ'
        };
        
        echo "  {$color}{$icon} {$message}{$reset}\n";
    }

    /**
     * Muestra un diálogo de confirmación
     */
    public function confirm(string $message, \App\SienteErpTui\Input\KeyHandler $keyHandler): bool
    {
        $borderCol = $this->color('border');
        $highlight = $this->color('highlight');
        $reset = $this->reset();
        $fKey = $this->color('function_key');
        
        $width = 60;
        $innerW = $width - 2;
        
        echo "\n\n";
        echo str_repeat(" ", 5) . "{$borderCol}╔" . str_repeat("═", $innerW) . "╗{$reset}\n";
        echo str_repeat(" ", 5) . "║{$highlight}" . str_pad(" CONFIRMACIÓN ", $innerW, " ", STR_PAD_BOTH) . "{$borderCol}║{$reset}\n";
        echo str_repeat(" ", 5) . "╠" . str_repeat("═", $innerW) . "╣{$reset}\n";
        
        $msgLines = explode("\n", wordwrap($message, $innerW - 4, "\n", true));
        foreach ($msgLines as $line) {
            echo str_repeat(" ", 5) . "║  " . str_pad($line, $innerW - 4) . "  {$borderCol}║{$reset}\n";
        }
        
        echo str_repeat(" ", 5) . "║" . str_repeat(" ", $innerW) . "{$borderCol}║{$reset}\n";
        
        $actions = "{$fKey}F10{$reset}=Confirmar  {$fKey}F12{$reset}=Cancelar";
        $cleanActions = preg_replace('/\033\[[0-9;:]*[mK]/', '', $actions);
        $pad = $innerW - mb_strwidth($cleanActions);
        
        echo str_repeat(" ", 5) . "║ " . $actions . str_repeat(" ", max(0, (int)$pad - 1)) . "{$borderCol}║{$reset}\n";
        echo str_repeat(" ", 5) . "╚" . str_repeat("═", $innerW) . "╝{$reset}\n\n";
        
        while (true) {
            $rawKey = $keyHandler->waitForKey();
            $key = \App\SienteErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
            
            if ($key === 'F10') return true;
            if ($key === 'F12' || $key === 'ESC') return false;
        }
    /**
     * Calcula el ancho visual de una cadena ignorando códigos ANSI
     */
    public function strWidth(string $text): int
    {
        // Limpiar códigos ANSI: \e [ ... letra , \e O letra, \e ( letra
        $clean = preg_replace([
            '/\x1b\[[0-9;?]*[A-Za-z~]/',
            '/\x1bO[A-Za-z]/',
            '/\x1b\([A-Z]/',
            '/\x1b\][0-9;]*\x07/' // OSC sequences
        ], '', $text);
        
        return (int)mb_strwidth($clean);
    }
}
