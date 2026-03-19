<?php

namespace App\NexErpTui\Display;

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
            self::MODERN => [
                // Tema moderno con paleta completa
                'border' => "\033[36m",        // Cyan para bordes
                'title' => "\033[1;33m",       // Amarillo brillante para títulos
                'text' => "\033[37m",          // Blanco para texto normal
                'highlight' => "\033[1;37m",   // Blanco brillante para resaltado
                'selected' => "\033[1;33m",    // Amarillo brillante para selección
                'company' => "\033[1;37m",     // Blanco brillante para empresa
                'datetime' => "\033[37m",      // Blanco para fecha/hora
                'menu_selected' => "\033[1;33m", // Amarillo brillante para menú seleccionado
                'menu_normal' => "\033[37m",   // Blanco para menú normal
                'function_key' => "\033[32m",  // Verde para teclas de función
                'error' => "\033[1;31m",       // Rojo brillante para errores
                'success' => "\033[1;32m",     // Verde brillante para éxito
                'warning' => "\033[1;33m",     // Amarillo brillante para advertencias
                'info' => "\033[1;36m",        // Cyan brillante para información
                'data' => "\033[37m",          // Blanco para datos
                'label' => "\033[36m",         // Cyan para etiquetas
                'value' => "\033[1;37m",       // Blanco brillante para valores
                'separator' => "\033[36m",     // Cyan para separadores
                'reset' => "\033[0m",
            ],
            default => $this->loadTheme(self::MODERN),
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
