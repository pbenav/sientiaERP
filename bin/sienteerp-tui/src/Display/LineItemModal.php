<?php

namespace App\SienteErpTui\Display;

use App\SienteErpTui\Input\KeyHandler;
use App\SienteErpTui\Input\FunctionKeyMapper;

/**
 * LineItemModal - Modal para añadir/editar líneas de documento
 * 
 * Permite ver todos los campos a la vez y navegar con TAB
 */
class LineItemModal
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private AutocompleteField $autocomplete;
    private array $fields = [];
    private int $selectedProductIndex = 0;
    private int $width;
    private bool $isEditing = false;
    
    public function __construct(KeyHandler $keyHandler, Screen $screen, int $width = 80)
    {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->width = $width;
        $this->autocomplete = new AutocompleteField($keyHandler, $screen, 'BUSCAR PRODUCTO');
        
        // Inicializar campos
        $this->fields = [
            ['name' => 'producto', 'label' => 'Producto', 'value' => '', 'readonly' => false, 'type' => 'autocomplete'],
            ['name' => 'cantidad', 'label' => 'Cantidad', 'value' => '1', 'readonly' => false, 'type' => 'number'],
            ['name' => 'precio', 'label' => 'Precio Unit.', 'value' => '', 'readonly' => true, 'type' => 'currency'],
            ['name' => 'descuento', 'label' => 'Descuento %', 'value' => '0', 'readonly' => false, 'type' => 'number'],
            ['name' => 'total', 'label' => 'Total', 'value' => '', 'readonly' => true, 'type' => 'currency'],
        ];
    }
    
    /**
     * Ejecuta el modal
     * 
     * @param callable $searchCallback Callback para buscar productos
     * @return array|null Datos de la línea o null si se canceló
     */
    public function run(callable $searchCallback): ?array
    {
        // Resetear estado al inicio
        $this->selectedProduct = null;
        $this->currentFieldIndex = 0;
        $this->fields[0]['value'] = '';  // Producto
        $this->fields[1]['value'] = '1'; // Cantidad
        $this->fields[2]['value'] = '';  // Precio
        $this->fields[3]['value'] = '0'; // Descuento
        $this->fields[4]['value'] = '';  // Total
        
        $this->keyHandler->setRawMode(true);
        
        while (true) {
            $this->render();
            
            $rawKey = $this->keyHandler->waitForKey();
            $key = \App\SienteErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
            
            $currentField = &$this->fields[$this->currentFieldIndex];

            // --- MODO EDICIÓN ---
            if ($this->isEditing) {
                if ($key === 'ENTER') {
                    $this->isEditing = false;
                } elseif ($key === 'BACKSPACE' || $key === 'DELETE') {
                    if (mb_strlen($currentField['value']) > 0) {
                        $currentField['value'] = mb_substr($currentField['value'], 0, -1);
                        $this->calculateTotal();
                    }
                } elseif (strlen($key) === 1 && (is_numeric($key) || $key === ',' || $key === '.')) {
                    $currentField['value'] .= $key;
                    $this->calculateTotal();
                }
                continue;
            }

            // --- MODO NAVEGACIÓN ---
            if ($key === 'TAB' || $key === 'DOWN') {
                $this->nextField();
            } elseif ($key === 'SHIFT_TAB' || $key === 'UP') {
                $this->previousField();
            }
            elseif ($key === 'ENTER') {
                if (!$currentField['readonly']) {
                    if ($currentField['type'] === 'autocomplete') {
                        $product = $this->autocomplete->run($searchCallback);
                        if ($product) {
                            $this->selectedProduct = $product;
                            $this->fields[0]['value'] = $product['name'];
                            $this->fields[2]['value'] = number_format($product['price'], 2, ',', '.');
                            $this->calculateTotal();
                            $this->nextField();
                        }
                    } else {
                        $this->isEditing = true;
                    }
                }
            }
            elseif ($key === 'F10') {
                if ($this->validate()) {
                    return $this->getLineData();
                }
            }
            elseif ($key === 'F12' || $key === 'ESC') {
                return null;
            }
            elseif ($currentField['type'] === 'autocomplete' && $key === 'F5') {
                $product = $this->autocomplete->run($searchCallback);
                if ($product) {
                    $this->selectedProduct = $product;
                    $this->fields[0]['value'] = $product['name'];
                    $this->fields[2]['value'] = number_format($product['price'], 2, ',', '.');
                    $this->calculateTotal();
                    $this->nextField();
                }
            }
        }
    }
    
    /**
     * Renderiza el modal
     */
    private function render(): void
    {
        $this->screen->clear();
        
        $borderCol = $this->screen->color('border');
        $titleCol = $this->screen->color('title');
        $textCol = $this->screen->color('text');
        $highlightCol = $this->screen->color('highlight');
        $reset = $this->screen->reset();

        // Borde superior
        echo "{$borderCol}╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título
        $title = "AÑADIR LÍNEA AL DOCUMENTO";
        $titleLength = mb_strlen($title);
        $availableSpace = $this->width - 4;
        $totalPadding = $availableSpace - $titleLength;
        $leftPadding = (int)floor($totalPadding / 2) + 1;
        $rightPadding = (int)ceil($totalPadding / 2) + 1;
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "{$titleCol}" . $title . "{$borderCol}";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╠" . str_repeat("═", $this->width - 2) . "╣{$reset}\n\n";
        
        // Campos
        foreach ($this->fields as $index => $field) {
            $isCurrent = ($index === $this->currentFieldIndex);
            $indicator = $isCurrent ? "►" : " ";
            $labelColor = $isCurrent ? $highlightCol : $textCol;
            $valueColor = $isCurrent ? $this->screen->color('value') : $textCol;
            $editingCol = $this->screen->color('selected');
            
            echo "  {$indicator} {$labelColor}" . str_pad($field['label'] . ":", 20) . "{$reset}";
            
            $displayValue = $field['value'];
            if ($isCurrent && $this->isEditing) {
                echo "{$editingCol} {$displayValue} _ {$reset}";
            } else {
                echo "{$valueColor}{$displayValue}{$reset}";
            }
            
            if ($isCurrent && $field['type'] === 'autocomplete' && empty($field['value'])) {
                echo "  {$this->screen->color('info')}(ENTER o F5 para buscar){$reset}";
            }
            
            echo "\n";
        }
        
        echo "\n";
        
        // Barra de funciones
        echo "{$borderCol}╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo "║ {$reset}";
        
        $modeStr = $this->isEditing ? "{$this->screen->color('selected')}MODO EDICIÓN{$reset}" : "{$this->screen->color('info')}MODO NAVEGACIÓN{$reset}";
        $fKey = $this->screen->color('function_key');

        $functions = [
            "{$fKey}F5{$reset}={$textCol}Buscar",
            "{$fKey}F10{$reset}={$textCol}Añadir",
            "{$fKey}F12{$reset}={$textCol}Salir",
            $modeStr
        ];
        
        $functionText = implode("  ", $functions);
        $cleanText = preg_replace('/\033\[[0-9;:]*[mK]/', '', $functionText);
        $padding = $this->width - 4 - mb_strwidth($cleanText);
        
        echo $functionText . str_repeat(" ", max(0, (int)$padding));
        echo " {$borderCol}║{$reset}\n";
        echo "{$borderCol}╚" . str_repeat("═", $this->width - 2) . "╝{$reset}\n";
    }
    
    /**
     * Avanza al siguiente campo
     */
    private function nextField(): void
    {
        do {
            $this->currentFieldIndex = ($this->currentFieldIndex + 1) % count($this->fields);
        } while ($this->fields[$this->currentFieldIndex]['readonly']);
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
     * Calcula el total de la línea
     */
    private function calculateTotal(): void
    {
        if (!$this->selectedProduct) {
            return;
        }
        
        $cantidad = (float)str_replace(',', '.', $this->fields[1]['value'] ?: '0');
        $precio = $this->selectedProduct['price'];
        $descuento = (float)str_replace(',', '.', $this->fields[3]['value'] ?: '0');
        
        $subtotal = $cantidad * $precio;
        $total = $subtotal * (1 - $descuento / 100);
        
        $this->fields[4]['value'] = number_format($total, 2, ',', '.');
    }
    
    /**
     * Valida los campos
     */
    private function validate(): bool
    {
        if (!$this->selectedProduct) {
            return false;
        }
        
        $cantidad = (float)str_replace(',', '.', $this->fields[1]['value'] ?: '0');
        if ($cantidad <= 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtiene los datos de la línea
     */
    private function getLineData(): array
    {
        $cantidad = (float)str_replace(',', '.', $this->fields[1]['value']);
        $descuento = (float)str_replace(',', '.', $this->fields[3]['value'] ?: '0');
        
        return [
            'product_id' => $this->selectedProduct['id'],
            'codigo' => $this->selectedProduct['sku'],
            'descripcion' => $this->selectedProduct['name'],
            'cantidad' => $cantidad,
            'precio_unitario' => $this->selectedProduct['price'],
            'descuento' => $descuento,
            'iva' => $this->selectedProduct['tax_rate'] ?? 21,
        ];
    }
    
    /**
     * Calcula longitud sin códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    }
}
