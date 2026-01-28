<?php

namespace App\SientiaErpTui\Display;

use App\SientiaErpTui\Input\KeyHandler;
use App\SientiaErpTui\Input\FunctionKeyMapper;

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
    private int $currentFieldIndex = 0;
    private ?array $selectedProduct = null;
    private int $width;
    
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
            $key = FunctionKeyMapper::mapKey($rawKey);
            
            $currentField = &$this->fields[$this->currentFieldIndex];  // IMPORTANTE: Usar referencia
            
            // Navegación
            if ($key === 'TAB' || $key === 'ENTER') {
                $this->nextField();
            } elseif ($key === 'SHIFT_TAB') {
                $this->previousField();
            }
            // Guardar
            elseif ($key === 'F10') {
                if ($this->validate()) {
                    return $this->getLineData();
                }
            }
            // Cancelar
            elseif ($key === 'F12' || $key === 'ESC') {
                return null;
            }
            // Autocompletado de producto
            elseif ($currentField['type'] === 'autocomplete' && ($key === 'F5' || (strlen($key) === 1 && ord($key) >= 32))) {
                $product = $this->autocomplete->run($searchCallback);
                if ($product) {
                    $this->selectedProduct = $product;
                    $this->fields[0]['value'] = $product['name'];
                    $this->fields[2]['value'] = number_format($product['price'], 2, ',', '.');
                    $this->calculateTotal();
                    $this->nextField();
                }
            }
            // Editar campo numérico
            elseif (!$currentField['readonly'] && $currentField['type'] !== 'autocomplete') {
                if ($key === 'BACKSPACE' || $key === 'DELETE') {
                    $currentValue = $this->fields[$this->currentFieldIndex]['value'];
                    if (mb_strlen($currentValue) > 0) {
                        $newValue = mb_substr($currentValue, 0, -1);
                        $this->fields[$this->currentFieldIndex]['value'] = $newValue;
                        $this->calculateTotal();
                    }
                } elseif (strlen($key) === 1 && (is_numeric($key) || $key === ',' || $key === '.')) {
                    $this->fields[$this->currentFieldIndex]['value'] .= $key;
                    $this->calculateTotal();
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
        
        // Borde superior
        echo "\033[36m";
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título
        $title = "AÑADIR LÍNEA AL DOCUMENTO";
        $titleLength = mb_strlen($title);
        $availableSpace = $this->width - 4;
        $totalPadding = $availableSpace - $titleLength;
        $leftPadding = (int)floor($totalPadding / 2) + 1;
        $rightPadding = (int)ceil($totalPadding / 2) + 1;
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $title . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n\n";
        
        // Campos
        foreach ($this->fields as $index => $field) {
            $isCurrent = ($index === $this->currentFieldIndex);
            $indicator = $isCurrent ? "►" : " ";
            $labelColor = $isCurrent ? "\033[1;33m" : "\033[37m";
            $valueColor = $isCurrent ? "\033[1;33m" : "\033[33m";
            
            echo "  {$indicator} {$labelColor}" . str_pad($field['label'] . ":", 20) . "\033[0m";
            
            $displayValue = $field['value'];
            if ($field['readonly']) {
                echo "{$valueColor}{$displayValue}\033[0m";
            } else {
                $cursor = $isCurrent ? "_" : "";
                echo "{$valueColor}{$displayValue}{$cursor}\033[0m";
            }
            
            // Ayuda contextual
            if ($isCurrent && $field['type'] === 'autocomplete' && empty($field['value'])) {
                echo "  \033[90m(F5 o escribe para buscar)\033[0m";
            }
            
            echo "\n";
        }
        
        echo "\n";
        
        // Barra de funciones
        echo "\033[36m";
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo "║ \033[0m";
        
        $functions = [
            "\033[32mF5\033[0m=Buscar Producto",
            "\033[32mF10\033[0m=Añadir",
            "\033[32mF12\033[0m=Cancelar",
            "\033[37mTAB\033[0m=Siguiente"
        ];
        
        $functionText = implode("  ", $functions);
        $padding = $this->width - 4 - $this->stripAnsiLength($functionText);
        
        echo $functionText . str_repeat(" ", max(0, $padding));
        echo "\033[36m ║\n";
        echo "╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n";
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
