<?php

namespace App\NexErpTui\Display;

/**
 * Window - Sistema de ventanas completas tipo AS/400
 * 
 * Dibuja ventanas completas con bordes, título, campos y barra de funciones
 * Similar a los sistemas 5250 de IBM AS/400 o 3270 de mainframes
 */
class Window
{
    private Screen $screen;
    private string $title;
    private array $fields = [];
    private array $functionKeys = [];
    private int $width;
    private int $height;
    private bool $hasTable = false;
    private array $tableData = [];
    private array $tableColumns = [];
    private int $selectedRow = 0;
    
    // Colores
    private const BORDER_COLOR = "\033[36m";      // Cyan
    private const TITLE_COLOR = "\033[1;37m";     // Bold White
    private const LABEL_COLOR = "\033[37m";       // White
    private const VALUE_COLOR = "\033[33m";       // Yellow
    private const SELECTED_COLOR = "\033[1;33m";  // Bold Yellow
    private const FUNCTION_COLOR = "\033[32m";    // Green
    private const RESET = "\033[0m";
    
    public function __construct(Screen $screen, string $title, int $width = 80, int $height = 24)
    {
        $this->screen = $screen;
        $this->title = $title;
        $this->width = $width;
        $this->height = $height;
        
        // Teclas de función por defecto (estilo AS/400)
        $this->functionKeys = [
            'F1' => 'Ayuda',
            'F2' => 'Editar',
            'F5' => 'Crear',
            'F6' => 'Modificar',
            'F8' => 'Eliminar',
            'F10' => 'Guardar',
            'F12' => 'Volver'
        ];
    }
    
    /**
     * Añade un campo de entrada a la ventana
     */
    public function addField(string $name, string $label, string $value = '', bool $required = false, bool $readonly = false): self
    {
        $this->fields[] = [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required,
            'readonly' => $readonly,
            'type' => 'text'
        ];
        return $this;
    }
    
    /**
     * Añade una tabla a la ventana
     */
    public function setTable(array $columns, array $data, int $selectedRow = 0): self
    {
        $this->hasTable = true;
        $this->tableColumns = $columns;
        $this->tableData = $data;
        $this->selectedRow = $selectedRow;
        return $this;
    }
    
    /**
     * Configura las teclas de función disponibles
     */
    public function setFunctionKeys(array $keys): self
    {
        $this->functionKeys = $keys;
        return $this;
    }
    
    /**
     * Dibuja la ventana completa
     */
    public function render(): void
    {
        $this->screen->clear();
        
        // Borde superior
        echo self::BORDER_COLOR;
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título centrado con padding dinámico
        $titleLength = mb_strlen($this->title);
        $availableSpace = $this->width - 4; // Espacio entre los bordes (║ + espacio + espacio + ║)
        
        if ($titleLength > $availableSpace) {
            // Si el título es muy largo, truncarlo
            $displayTitle = mb_substr($this->title, 0, $availableSpace);
            $leftPadding = 1;
            $rightPadding = 1;
        } else {
            // Centrar el título
            $displayTitle = $this->title;
            $totalPadding = $availableSpace - $titleLength;
            $leftPadding = (int)floor($totalPadding / 2) + 1;
            $rightPadding = (int)ceil($totalPadding / 2) + 1;
        }
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo self::TITLE_COLOR . $displayTitle . self::BORDER_COLOR;
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        // Línea separadora
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo self::RESET;
        
        // Contenido
        if ($this->hasTable) {
            $this->renderTable();
        } else {
            $this->renderFields();
        }
        
        // Barra de funciones (siempre en la parte inferior)
        $this->renderFunctionBar();
    }
    
    /**
     * Renderiza campos de formulario
     */
    private function renderFields(): void
    {
        echo "\n";
        
        foreach ($this->fields as $index => $field) {
            $label = $field['label'];
            $value = $field['value'];
            $required = $field['required'] ? '*' : ' ';
            
            echo "  " . self::LABEL_COLOR . $required . " " . str_pad($label . ":", 25);
            echo self::VALUE_COLOR . $value . self::RESET . "\n";
            
            if (($index + 1) % 3 == 0) {
                echo "\n"; // Espacio cada 3 campos
            }
        }
        
        echo "\n";
    }
    
    /**
     * Renderiza una tabla de datos
     */
    private function renderTable(): void
    {
        // Cabecera de la tabla
        echo "\n  " . self::BORDER_COLOR;
        
        foreach ($this->tableColumns as $col) {
            echo str_pad($col['label'], $col['width']);
        }
        echo self::RESET . "\n";
        
        // Línea separadora
        echo "  " . self::BORDER_COLOR;
        $totalWidth = array_sum(array_column($this->tableColumns, 'width'));
        echo str_repeat("─", $totalWidth) . self::RESET . "\n";
        
        // Datos
        if (empty($this->tableData)) {
            echo "\n  " . self::VALUE_COLOR . "No hay registros para mostrar" . self::RESET . "\n\n";
        } else {
            foreach ($this->tableData as $rowIndex => $row) {
                $isSelected = ($rowIndex === $this->selectedRow);
                $prefix = $isSelected ? self::SELECTED_COLOR . "► " : "  ";
                $color = $isSelected ? self::SELECTED_COLOR : self::LABEL_COLOR;
                
                echo $prefix . $color;
                
                foreach ($this->tableColumns as $col) {
                    $value = $row[$col['field']] ?? '';
                    
                    // Formatear según tipo
                    if (isset($col['format'])) {
                        $value = $this->formatValue($value, $col['format']);
                    }
                    
                    echo str_pad(substr($value, 0, $col['width'] - 1), $col['width']);
                }
                
                echo self::RESET . "\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Formatea valores según tipo
     */
    private function formatValue($value, string $format): string
    {
        return match($format) {
            'currency' => number_format((float)$value, 2, ',', '.') . ' €',
            'percentage' => $value . '%',
            'date' => date('d/m/Y', strtotime($value)),
            default => (string)$value
        };
    }
    
    /**
     * Renderiza la barra de funciones en la parte inferior
     */
    private function renderFunctionBar(): void
    {
        // Línea separadora
        echo self::BORDER_COLOR;
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\n";
        
        // Funciones disponibles
        echo "║ " . self::RESET;
        
        $functions = [];
        foreach ($this->functionKeys as $key => $label) {
            $functions[] = self::FUNCTION_COLOR . $key . self::RESET . 
                          self::LABEL_COLOR . "=" . $label . self::RESET;
        }
        
        $functionText = implode("  ", $functions);
        $padding = $this->width - 4 - $this->stripAnsiLength($functionText);
        
        echo $functionText . str_repeat(" ", max(0, $padding));
        echo self::BORDER_COLOR . " ║\n";
        
        // Borde inferior
        echo "╚" . str_repeat("═", $this->width - 2) . "╝";
        echo self::RESET . "\n";
    }
    
    /**
     * Calcula la longitud de un string sin contar códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    }
    
    /**
     * Obtiene el índice de la fila seleccionada
     */
    public function getSelectedRow(): int
    {
        return $this->selectedRow;
    }
    
    /**
     * Establece la fila seleccionada
     */
    public function setSelectedRow(int $row): self
    {
        $this->selectedRow = max(0, min($row, count($this->tableData) - 1));
        return $this;
    }
    
    /**
     * Obtiene los datos de la fila seleccionada
     */
    public function getSelectedData(): ?array
    {
        return $this->tableData[$this->selectedRow] ?? null;
    }
}
