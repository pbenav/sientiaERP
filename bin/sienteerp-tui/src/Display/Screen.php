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
        // Limpiar pantalla y posicionar cursor en 1,1
        echo "\033[2J\033[H";
    }

    public function render(string $content): void
    {
        echo $content;
    }

    public function showMessage(string $message, string $type = 'info'): void
    {
        $color = match($type) {
            'success' => $this->color('success'),
            'error' => $this->color('error'),
            'warning' => $this->color('warning'),
            default => $this->color('info'),
        };
        $reset = $this->reset();
        
        echo "{$color}  {$message}{$reset}\n";
    }
}
