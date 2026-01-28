<?php

namespace App\SienteErpTui\Input;

/**
 * FunctionKeyMapper - Mapeo de teclas de función estilo AS/400
 * 
 * Maneja el mapeo de teclas de función F1-F12 y combinaciones especiales
 * siguiendo el estándar de sistemas IBM AS/400 y mainframes
 */
class FunctionKeyMapper
{
    // Mapeo de secuencias ANSI a teclas de función
    private const KEY_MAP = [
        // Teclas de función F1-F12
        "\eOP" => 'F1',           // F1
        "\eOQ" => 'F2',           // F2
        "\eOR" => 'F3',           // F3
        "\eOS" => 'F4',           // F4
        "\e[15~" => 'F5',         // F5
        "\e[17~" => 'F6',         // F6
        "\e[18~" => 'F7',         // F7
        "\e[19~" => 'F8',         // F8
        "\e[20~" => 'F9',         // F9
        "\e[21~" => 'F10',        // F10
        "\e[23~" => 'F11',        // F11
        "\e[24~" => 'F12',        // F12
        
        // Teclas de cursor
        "\e[A" => 'UP',           // Cursor arriba
        "\e[B" => 'DOWN',         // Cursor abajo
        "\e[C" => 'RIGHT',        // Cursor derecha
        "\e[D" => 'LEFT',         // Cursor izquierda
        
        // Teclas especiales
        "\t" => 'TAB',            // Tabulador
        "\e[Z" => 'SHIFT_TAB',    // Shift+Tab (backtab)
        "\n" => 'ENTER',          // Enter
        "\r" => 'ENTER',          // Enter (alternativo)
        "\x7f" => 'BACKSPACE',    // Backspace (DEL en ASCII)
        "\x08" => 'BACKSPACE',    // Backspace (BS en ASCII)
        "\e[3~" => 'DELETE',      // Delete
        "\e" => 'ESC',            // Escape
        "\x1b" => 'ESC',          // Escape (alternativo)
        
        // Control + teclas
        "\e[1;5C" => 'CTRL_RIGHT',  // Ctrl+→
        "\e[1;5D" => 'CTRL_LEFT',   // Ctrl+←
        "\e[1;5A" => 'CTRL_UP',     // Ctrl+↑
        "\e[1;5B" => 'CTRL_DOWN',   // Ctrl+↓
        
        // Otras teclas útiles
        "\e[H" => 'HOME',         // Home
        "\e[F" => 'END',          // End
        "\e[5~" => 'PAGE_UP',     // Page Up
        "\e[6~" => 'PAGE_DOWN',   // Page Down
        "\e[2~" => 'INSERT',      // Insert
        "\e[3~" => 'DELETE',      // Delete
    ];
    
    /**
     * Convierte una secuencia de tecla raw a un nombre de tecla
     */
    public static function mapKey(string $rawKey): string
    {
        // Primero intentar mapeo directo
        if (isset(self::KEY_MAP[$rawKey])) {
            return self::KEY_MAP[$rawKey];
        }
        
        // Si es un carácter imprimible, devolverlo tal cual
        if (strlen($rawKey) === 1 && ord($rawKey) >= 32 && ord($rawKey) <= 126) {
            return $rawKey;
        }
        
        // Desconocido - devolver la secuencia raw para debug
        return 'UNKNOWN';
    }
    
    /**
     * Verifica si una tecla es una tecla de función (F1-F12)
     */
    public static function isFunctionKey(string $key): bool
    {
        return preg_match('/^F([1-9]|1[0-2])$/', $key) === 1;
    }
    
    /**
     * Verifica si una tecla es una tecla de cursor
     */
    public static function isCursorKey(string $key): bool
    {
        return in_array($key, ['UP', 'DOWN', 'LEFT', 'RIGHT']);
    }
    
    /**
     * Verifica si una tecla es TAB o SHIFT_TAB
     */
    public static function isTabKey(string $key): bool
    {
        return in_array($key, ['TAB', 'SHIFT_TAB']);
    }
    
    /**
     * Obtiene el número de una tecla de función (F1 -> 1, F12 -> 12)
     */
    public static function getFunctionKeyNumber(string $key): ?int
    {
        if (preg_match('/^F(\d+)$/', $key, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
    
    /**
     * Obtiene una descripción legible de una tecla
     */
    public static function getKeyDescription(string $key): string
    {
        return match($key) {
            'F1' => 'F1 (Ayuda)',
            'F2' => 'F2 (Editar)',
            'F5' => 'F5 (Crear)',
            'F6' => 'F6 (Modificar)',
            'F8' => 'F8 (Eliminar)',
            'F10' => 'F10 (Guardar)',
            'F12' => 'F12 (Volver)',
            'ENTER' => 'Enter',
            'TAB' => 'Tab',
            'SHIFT_TAB' => 'Shift+Tab',
            'ESC' => 'Escape',
            'UP' => 'Cursor Arriba',
            'DOWN' => 'Cursor Abajo',
            'LEFT' => 'Cursor Izquierda',
            'RIGHT' => 'Cursor Derecha',
            'CTRL_RIGHT' => 'Ctrl+→ (Cerrar)',
            default => $key
        };
    }
}
