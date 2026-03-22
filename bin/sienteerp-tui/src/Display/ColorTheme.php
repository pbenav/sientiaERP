<?php

namespace App\SienteErpTui\Display;

/**
 * ColorTheme - Temas de color para la interfaz TUI
 * 
 * Soporta diferentes esquemas de color tipo terminales clásicos:
 * - IBM Green (verde sobre negro)
 * - IBM Amber (ámbar sobre negro)
 * - Modern (cyan/azul sobre negro)
 */
class ColorTheme
{
    // Temas disponibles
    public const IBM_GREEN = 'ibm_green';
    public const IBM_AMBER = 'ibm_amber';
    public const MODERN = 'modern';
    
    private string $currentTheme;
    private array $colors;
    
    public function __construct(string $theme = self::IBM_GREEN)
    {
        $this->currentTheme = $theme;
        $this->loadTheme($theme);
    }
    
    /**
     * Carga un tema de color
     */
    private function loadTheme(string $theme): void
    {
        $this->colors = match($theme) {
            self::IBM_GREEN => [
                // Paleta completa IBM 5250/3270: Verde, Rojo, Amarillo, Azul, Rosa, Cyan, Blanco
                'border' => "\033[32m",        // Verde para bordes (color principal)
                'title' => "\033[1;37m",       // Blanco brillante para títulos principales
                'text' => "\033[37m",          // Blanco para texto normal
                'highlight' => "\033[1;33m",   // Amarillo brillante para resaltado
                'selected' => "\033[1;33m",    // Amarillo brillante para selección
                'company' => "\033[1;36m",     // Cyan brillante para empresa
                'datetime' => "\033[36m",      // Cyan para fecha/hora
                'menu_selected' => "\033[1;33m", // Amarillo brillante para menú seleccionado
                'menu_normal' => "\033[32m",   // Verde para menú normal
                'function_key' => "\033[1;32m", // Verde brillante para teclas de función
                'error' => "\033[1;31m",       // Rojo brillante para errores
                'success' => "\033[1;32m",     // Verde brillante para éxito
                'warning' => "\033[1;33m",     // Amarillo brillante para advertencias
                'info' => "\033[1;36m",        // Cyan brillante para información
                'data' => "\033[37m",          // Blanco para datos
                'label' => "\033[32m",         // Verde para etiquetas
                'value' => "\033[1;37m",       // Blanco brillante para valores
                'separator' => "\033[32m",     // Verde para separadores
                'reset' => "\033[0m",
            ],
            self::IBM_AMBER => [
                // Tema ámbar con paleta de colores
                'border' => "\033[33m",        // Amarillo/ámbar para bordes
                'title' => "\033[1;37m",       // Blanco brillante para títulos
                'text' => "\033[37m",          // Blanco para texto normal
                'highlight' => "\033[1;33m",   // Amarillo brillante para resaltado
                'selected' => "\033[1;33m",    // Amarillo brillante para selección
                'company' => "\033[1;33m",     // Amarillo brillante para empresa
                'datetime' => "\033[33m",      // Amarillo para fecha/hora
                'menu_selected' => "\033[1;37m", // Blanco brillante para menú seleccionado
                'menu_normal' => "\033[33m",   // Amarillo para menú normal
                'function_key' => "\033[1;33m", // Amarillo brillante para teclas de función
                'error' => "\033[1;31m",       // Rojo brillante para errores
                'success' => "\033[1;32m",     // Verde brillante para éxito
                'warning' => "\033[1;33m",     // Amarillo brillante para advertencias
                'info' => "\033[1;36m",        // Cyan brillante para información
                'data' => "\033[37m",          // Blanco para datos
                'label' => "\033[33m",         // Amarillo para etiquetas
                'value' => "\033[1;37m",       // Blanco brillante para valores
                'separator' => "\033[33m",     // Amarillo para separadores
                'reset' => "\033[0m",
            ],
/* --- VIBRANT THEME --- */
            'vibrant' => [
                'main_bg' => "\033[48;5;17m",       // Fondo azul oscuro/profundo
                'border' => "\033[38;5;93m",        // Púrpura intenso
                'title' => "\033[1;38;5;51m",      // Cyan eléctrico brillante
                'text' => "\033[38;5;15m",         // Blanco puro
                'highlight' => "\033[1;38;5;208m",   // Naranja vibrante
                'selected' => "\033[48;5;93m\033[38;5;255m", // Fondo púrpura, texto blanco
                'company' => "\033[1;38;5;51m",      // Cyan eléctrico para empresa
                'datetime' => "\033[38;5;129m",     // Violeta suave
                'header_bg' => "\033[48;5;21m",     // Fondo azul eléctrico para cabecera
                'header_fg' => "\033[1;38;5;255m",   // Blanco brillante sobre fondo azul
                'menu_selected' => "\033[48;5;51m\033[38;5;232m", // Fondo cyan, texto casi negro
                'menu_normal' => "\033[38;5;93m",     // Púrpura para menú
                'function_key' => "\033[1;38;5;45m",  // Turquesa para teclas de función
                'error' => "\033[1;38;5;196m",       // Rojo intenso
                'success' => "\033[1;38;5;46m",      // Verde lima brillante
                'warning' => "\033[1;38;5;226m",     // Amarillo puro
                'info' => "\033[1;38;5;51m",         // Cyan brillante
                'data' => "\033[38;5;253m",          // Gris muy claro para datos
                'label' => "\033[38;5;45m",          // Turquesa para etiquetas
                'value' => "\033[1;38;5;208m",       // Naranja para valores
                'separator' => "\033[38;5;93m",      // Púrpura para separadores
                'reset' => "\033[0m\033[48;5;17m",   // Reset vuelve al fondo azul
            ],
            default => $this->loadTheme('vibrant'),
        };
    }
    
    /**
     * Obtiene un color del tema actual
     */
    public function get(string $key): string
    {
        return $this->colors[$key] ?? $this->colors['reset'];
    }
    
    /**
     * Obtiene todos los colores del tema
     */
    public function getAll(): array
    {
        return $this->colors;
    }
    
    /**
     * Cambia el tema actual
     */
    public function setTheme(string $theme): void
    {
        $this->currentTheme = $theme;
        $this->loadTheme($theme);
    }
    
    /**
     * Obtiene el tema actual
     */
    public function getCurrentTheme(): string
    {
        return $this->currentTheme;
    }
}
