<?php

namespace App\SienteErpTui\Display;

use App\SienteErpTui\Input\KeyHandler;
use App\SienteErpTui\Input\FunctionKeyMapper;

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
    private bool $isEditing = false;
    private bool $allSelected = false; 
    
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
        $this->screen->clear('form_bg');
        
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset('form_bg');
        
        // Borde superior
        echo "{$borderCol}╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
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
        
        echo "{$borderCol}║{$reset}" . str_repeat(" ", $leftPadding);
        echo $displayTitle;
        echo str_repeat(" ", $rightPadding) . "{$borderCol}║{$reset}\n";
        
        // Separador
        echo "{$borderCol}╠" . str_repeat("═", $this->width - 2) . "╣{$reset}\n";
        
        // Campos (Bufferizar output para envolver en marco)
        ob_start();
        echo "\n";
        foreach ($this->fields as $index => $field) {
            $this->renderField($index, $field);
        }
        echo "\n";
        $fieldsOutput = ob_get_clean();
        
        $lines = explode("\n", $fieldsOutput);
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset();
        
        foreach ($lines as $line) {
            if ($line === '' && $index === count($lines)-1) continue; 
            
            $visibleLen = $this->screen->strWidth($line);
            $padding = max(0, $this->width - 2 - $visibleLen);
            
            echo "{$borderCol}║{$reset}" . $line . str_repeat(" ", (int)$padding) . "{$borderCol}║{$reset}\n";
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
        
        $labelCol = $isCurrent ? $this->screen->color('highlight') : $this->screen->color('text');
        $valueCol = $isCurrent ? $this->screen->color('value') : $this->screen->color('text');
        $editingCol = $this->screen->color('selected');
        $reset = $this->screen->reset();
        
        $indicator = $isCurrent ? "►" : " ";
        
        echo "  {$indicator} {$labelCol}{$required} " . str_pad($field['label'] . ":", 25) . "{$reset}";
        
        $value = $field['value'];
        if ($field['type'] === 'password') {
            $value = str_repeat('*', mb_strlen($value));
        }
        
        if ($isCurrent && $this->isEditing) {
            echo "{$editingCol} {$value} _ {$reset}";
        } else {
            echo "{$valueCol}{$value}{$reset}";
        }
        
        echo "\n";
        
        if ($field['error']) {
            echo "     {$this->screen->color('error')}⚠ {$field['error']}{$reset}\n";
        }
        
        if (($index + 1) % 3 == 0) {
            echo "\n";
        }
    }
    
    /**
     * Renderiza la barra de funciones
     */
    private function renderFunctionBar(): void
    {
        $borderCol = $this->screen->color('border');
        $fKeyCol = $this->screen->color('function_key');
        $textCol = $this->screen->color('text');
        $reset = $this->screen->reset();

        echo "{$borderCol}╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo "║ {$reset}";
        
        $modeStr = $this->isEditing ? "{$this->screen->color('selected')}MODO EDICIÓN{$reset}" : "{$this->screen->color('info')}MODO NAVEGACIÓN{$reset}";

        $functions = [
            "{$fKeyCol}F10{$reset}={$textCol}Guardar",
            "{$fKeyCol}F12{$reset}={$textCol}Cancelar",
            "{$fKeyCol}ENTER{$reset}=" . ($this->isEditing ? "{$textCol}Confirmar" : "{$textCol}Editar"),
            $modeStr
        ];
        
        $functionText = implode("  ", $functions);
        $cleanTextWidth = $this->screen->strWidth($functionText);
        $padding = $this->width - 4 - $cleanTextWidth;
        
        echo $functionText . str_repeat(" ", max(0, (int)$padding));
        echo " {$borderCol}║{$reset}\n";
        echo "{$borderCol}╚" . str_repeat("═", $this->width - 2) . "╝{$reset}\n";
    }
    
    /**
     * Maneja la entrada del usuario
     */
    private function handleInput(): void
    {
        $rawKey = $this->keyHandler->waitForKey();
        $key = \App\SienteErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
        
        // Acciones globales (siempre disponibles)
        if ($key === 'F10') {
            if ($this->validate()) {
                $this->action = 'save';
                $this->isRunning = false;
            }
            return;
        } elseif ($key === 'F12' || $key === 'ESC') {
            $this->action = 'cancel';
            $this->isRunning = false;
            return;
        }

        // --- MODO EDICIÓN ---
        if ($this->isEditing) {
            if ($key === 'ENTER') {
                $this->isEditing = false;
            } elseif ($key === 'BACKSPACE' || $key === 'DELETE') {
                $this->deleteChar();
                $this->allSelected = false;
            } elseif (in_array($key, ['LEFT', 'RIGHT', 'UP', 'DOWN', 'HOME', 'END'])) {
                $this->allSelected = false;
            } elseif (strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 255) {
                // Soportar caracteres extendidos/especiales
                $this->addChar($key);
            }
            return;
        }

        // --- MODO NAVEGACIÓN ---
        if ($key === 'ENTER') {
            $field = $this->fields[$this->currentFieldIndex];
            if (!$field['readonly']) {
                $this->isEditing = true;
                $this->allSelected = true; // Marcar para sobreescribir al empezar a escribir
            }
        } elseif ($key === 'TAB' || $key === 'DOWN') {
            $this->nextField();
        } elseif ($key === 'SHIFT_TAB' || $key === 'UP') {
            $this->previousField();
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
        
        // Si todo está seleccionado, el primer carácter sobreescribe
        if ($this->allSelected) {
            $field['value'] = $char;
            $this->allSelected = false;
        } else {
            $field['value'] .= $char;
        }
        
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
        return $this->screen->strWidth($text);
    }
    
    /**
     * Obtiene los valores del formulario
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
