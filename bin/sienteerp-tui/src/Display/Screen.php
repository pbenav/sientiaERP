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

    private array $colors;

    public function __construct(array $colors = [])
    {
        $this->colors = $colors;
    }

    public function clear(): void
    {
        // Usamos Home + Clear Down en lugar de Clear Screen total para reducir parpadeo
        echo "\033[H\033[J"; 
    }

    public function render(string $content): void
    {
        echo $content;
    }

    public function showMessage(string $message, string $type = 'info'): void
    {
        $color = match($type) {
            'success' => $this->colors['fg_green'] ?? "\033[32m",
            'error' => $this->colors['fg_red'] ?? "\033[31m",
            'warning' => $this->colors['fg_yellow'] ?? "\033[33m",
            default => $this->colors['fg_white'] ?? "\033[37m",
        };
        $reset = $this->colors['reset'] ?? "\033[0m";
        
        echo "{$color}  {$message}{$reset}\n";
    }
}
