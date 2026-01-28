<?php

namespace App\SientiaErpTui\Display;

use App\SientiaErpTui\Input\KeyHandler;
use App\SientiaErpTui\Input\FunctionKeyMapper;

/**
 * FormController - Controlador de formularios tipo AS/400
 * 
 * Maneja la navegación entre campos con TAB/Shift+TAB,
 * entrada de datos y validación en formularios estilo mainframe
 */
class FormController
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private array $fields = [];
    private int $currentFieldIndex = 0;
    private array $values = [];
    private string $title;
    private int $width;
    
    // Estados del formulario
    private bool $isRunning = true;
    private ?string $action = null; // 'save', 'cancel', etc.
    
    public function __construct(KeyHandler $keyHandler, Screen $screen, string $title, int $width = 80)
    {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->title = $title;
        $this->width = $width;
    }
    
    /**
     * Añade un campo al formulario
     */
    public function addField(
        string $name,
        string $label,
        string $defaultValue = '',
        bool $required = false,
        ?callable $validator = null,
        bool $readonly = false,
        string $type = 'text'
    ): self {
        $this->fields[] = [
            'name' => $name,
            'label' => $label,
            'value' => $defaultValue,
            'required' => $required,
            'validator' => $validator,
            'readonly' => $readonly,
            'type' => $type,
            'error' => null
        ];
        
        $this->values[$name] = $defaultValue;
        
        return $this;
    }
    
    /**
     * Ejecuta el formulario y devuelve los valores o null si se canceló
     */
    public function run(): ?array
    {
        $this->keyHandler->setRawMode(true);
        $this->isRunning = true;
        $this->action = null;
        
        while ($this->isRunning) {
            $this->render();
            $this->handleInput();
        }
        
        // Si se guardó, devolver valores; si se canceló, null
        return $this->action === 'save' ? $this->values : null;
    }
    
    /**
     * Renderiza el formulario completo
     */
    private function render(): void
    {
        $this->screen->clear();
        
        // Borde superior
        echo "\033[36m"; // Cyan
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título centrado con padding dinámico
        $titleLength = mb_strlen($this->title);
        $availableSpace = $this->width - 2; // -2 bordes 
        
        $displayTitle = $this->title;
        if ($titleLength > $availableSpace) {
            $displayTitle = mb_substr($this->title, 0, $availableSpace);
            $leftPadding = 1;
            $rightPadding = 1;
        } else {
            $totalPadding = max(0, $availableSpace - $titleLength);
            $leftPadding = (int)floor($totalPadding / 2);
            $rightPadding = (int)ceil($totalPadding / 2);
        }
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $displayTitle . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        // Separador
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n";
        
        // Campos (Bufferizar output para envolver en marco)
        ob_start();
        echo "\n";
        foreach ($this->fields as $index => $field) {
            $this->renderField($index, $field);
        }
        echo "\n";
        $fieldsOutput = ob_get_clean();
        
        $lines = explode("\n", $fieldsOutput);
        foreach ($lines as $line) {
            // Ignorar líneas vacías finales si hay muchas
            if ($line === '' && $index === count($lines)-1) continue; 
            
            // Calcular padding para llegar al borde derecho
            $visibleLen = $this->stripAnsiLength($line);
            $padding = max(0, $this->width - 2 - $visibleLen);
            
            echo "\033[36m║\033[0m" . $line . str_repeat(" ", $padding) . "\033[36m║\033[0m\n";
        }
        
        // Barra de funciones
        $this->renderFunctionBar();
    }
    
    /**
     * Renderiza un campo individual
     */
    private function renderField(int $index, array $field): void
    {
        $isCurrent = ($index === $this->currentFieldIndex);
        $required = $field['required'] ? '*' : ' ';
        
        // Color según estado
        $labelColor = $isCurrent ? "\033[1;33m" : "\033[37m"; // Amarillo brillante si es actual
        $valueColor = $isCurrent ? "\033[1;33m" : "\033[33m";
        
        // Indicador de campo actual
        $indicator = $isCurrent ? "►" : " ";
        
        // Label
        echo "  {$indicator} {$labelColor}{$required} " . str_pad($field['label'] . ":", 25) . "\033[0m";
        
        // Valor
        $value = $field['value'];
        if ($field['type'] === 'password') {
            $value = str_repeat('*', strlen($value));
        }
        
        // Si es el campo actual y está en modo edición, mostrar cursor
        if ($isCurrent && !$field['readonly']) {
            echo "{$valueColor}{$value}_\033[0m";
        } else {
            echo "{$valueColor}{$value}\033[0m";
        }
        
        echo "\n";
        
        // Mostrar error si existe
        if ($field['error']) {
            echo "     \033[31m⚠ {$field['error']}\033[0m\n";
        }
        
        // Espacio adicional cada 3 campos
        if (($index + 1) % 3 == 0) {
            echo "\n";
        }
    }
    
    /**
     * Renderiza la barra de funciones
     */
    private function renderFunctionBar(): void
    {
        echo "\033[36m";
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo "║ \033[0m";
        
        $functions = [
            "\033[32mF1\033[0m=Ayuda",
            "\033[32mF10\033[0m=Guardar",
            "\033[32mF12\033[0m=Cancelar",
            "\033[37mTAB\033[0m=Siguiente",
            "\033[37mShift+TAB\033[0m=Anterior"
        ];
        
        $functionText = implode("  ", $functions);
        $padding = $this->width - 4 - $this->stripAnsiLength($functionText);
        
        echo $functionText . str_repeat(" ", max(0, $padding));
        echo "\033[36m ║\n";
        echo "╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n";
    }
    
    /**
     * Maneja la entrada del usuario
     */
    private function handleInput(): void
    {
        $rawKey = $this->keyHandler->waitForKey();
        $key = FunctionKeyMapper::mapKey($rawKey);
        
        // Teclas de función
        if ($key === 'F10') {
            // Guardar
            if ($this->validate()) {
                $this->action = 'save';
                $this->isRunning = false;
            }
        } elseif ($key === 'F12' || $key === 'ESC') {
            // Cancelar
            $this->action = 'cancel';
            $this->isRunning = false;
        } elseif ($key === 'F1') {
            // Ayuda (por implementar)
            $this->showHelp();
        }
        // Navegación entre campos
        elseif ($key === 'TAB' || $key === 'ENTER') {
            $this->nextField();
        } elseif ($key === 'SHIFT_TAB') {
            $this->previousField();
        }
        // Edición del campo actual
        elseif ($key === 'BACKSPACE' || $key === 'DELETE') {
            $this->deleteChar();
        } elseif (strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            // Carácter imprimible
            $this->addChar($key);
        }
    }
    
    /**
     * Avanza al siguiente campo
     */
    private function nextField(): void
    {
        do {
            $this->currentFieldIndex = ($this->currentFieldIndex + 1) % count($this->fields);
        } while ($this->fields[$this->currentFieldIndex]['readonly'] && $this->currentFieldIndex !== 0);
    }
    
    /**
     * Retrocede al campo anterior
     */
    private function previousField(): void
    {
        do {
            $this->currentFieldIndex--;
            if ($this->currentFieldIndex < 0) {
                $this->currentFieldIndex = count($this->fields) - 1;
            }
        } while ($this->fields[$this->currentFieldIndex]['readonly']);
    }
    
    /**
     * Añade un carácter al campo actual
     */
    private function addChar(string $char): void
    {
        $field = &$this->fields[$this->currentFieldIndex];
        
        if ($field['readonly']) {
            return;
        }
        
        $field['value'] .= $char;
        $this->values[$field['name']] = $field['value'];
        $field['error'] = null; // Limpiar error al editar
    }
    
    /**
     * Elimina un carácter del campo actual
     */
    private function deleteChar(): void
    {
        $field = &$this->fields[$this->currentFieldIndex];
        
        if ($field['readonly'] || mb_strlen($field['value']) === 0) {
            return;
        }
        
        $field['value'] = mb_substr($field['value'], 0, -1);
        $this->values[$field['name']] = $field['value'];
        $field['error'] = null;
    }
    
    /**
     * Valida todos los campos
     */
    private function validate(): bool
    {
        $valid = true;
        
        foreach ($this->fields as $index => &$field) {
            $field['error'] = null;
            
            // Validar requerido
            if ($field['required'] && empty($field['value'])) {
                $field['error'] = 'Este campo es obligatorio';
                $valid = false;
                continue;
            }
            
            // Validar con función personalizada
            if ($field['validator'] && !empty($field['value'])) {
                $error = call_user_func($field['validator'], $field['value']);
                if ($error) {
                    $field['error'] = $error;
                    $valid = false;
                }
            }
        }
        
        return $valid;
    }
    
    /**
     * Muestra la ayuda contextual
     */
    private function showHelp(): void
    {
        // Por ahora solo muestra un mensaje simple
        // En el futuro se puede implementar ayuda contextual por campo
        $this->screen->clear();
        echo "\n\n";
        echo "  \033[1;36m╔════════════════════════════════════════════════════════════╗\033[0m\n";
        echo "  \033[1;36m║                         AYUDA                              ║\033[0m\n";
        echo "  \033[1;36m╚════════════════════════════════════════════════════════════╝\033[0m\n\n";
        echo "  \033[37mNavegación:\033[0m\n";
        echo "    TAB / Enter     - Siguiente campo\n";
        echo "    Shift+TAB       - Campo anterior\n\n";
        echo "  \033[37mAcciones:\033[0m\n";
        echo "    F10             - Guardar formulario\n";
        echo "    F12 / ESC       - Cancelar y volver\n\n";
        echo "  \033[37mEdición:\033[0m\n";
        echo "    Backspace       - Borrar carácter\n";
        echo "    Caracteres      - Escribir en el campo\n\n";
        echo "  \033[33mPresione cualquier tecla para continuar...\033[0m\n";
        
        $this->keyHandler->waitForKey();
    }
    
    /**
     * Calcula longitud sin códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    }
    
    /**
     * Obtiene los valores del formulario
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
