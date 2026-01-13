<?php

namespace App\NexErpTui\Display;

use App\NexErpTui\Input\KeyHandler;
use App\NexErpTui\Input\FunctionKeyMapper;

/**
 * DocumentLinesEditor - Editor de líneas de documento
 * 
 * Muestra las líneas como tabla y permite editar/eliminar con cursores
 */
class DocumentLinesEditor
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private LineItemModal $lineModal;
    private array $lines = [];
    private int $selectedIndex = 0;
    private int $width;
    private string $title;
    
    public function __construct(KeyHandler $keyHandler, Screen $screen, string $title = 'LÍNEAS DEL DOCUMENTO', int $width = 80)
    {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->title = $title;
        $this->width = $width;
        $this->lineModal = new LineItemModal($keyHandler, $screen);
    }
    
    /**
     * Ejecuta el editor de líneas
     * 
     * @param array $initialLines Líneas iniciales del documento
     * @param callable $searchCallback Callback para buscar productos
     * @return array|null Array de líneas modificadas o null si se canceló
     */
    public function run(array $initialLines, callable $searchCallback): ?array
    {
        $this->lines = $initialLines;
        $this->selectedIndex = 0;
        
        $this->keyHandler->setRawMode(true);
        
        while (true) {
            $this->render();
            
            $rawKey = $this->keyHandler->waitForKey();
            $key = FunctionKeyMapper::mapKey($rawKey);
            
            // Navegación
            if ($key === 'UP') {
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                }
            } elseif ($key === 'DOWN') {
                if ($this->selectedIndex < count($this->lines) - 1) {
                    $this->selectedIndex++;
                }
            }
            // Añadir línea
            elseif ($key === 'F5') {
                $newLine = $this->lineModal->run($searchCallback);
                if ($newLine) {
                    $this->lines[] = $newLine;
                    $this->selectedIndex = count($this->lines) - 1;
                }
            }
            // Editar línea
            elseif (($key === 'F2' || $key === 'F6') && !empty($this->lines)) {
                $this->editLine($searchCallback);
            }
            // Eliminar línea
            elseif ($key === 'F8' && !empty($this->lines)) {
                if ($this->confirmDelete()) {
                    array_splice($this->lines, $this->selectedIndex, 1);
                    if ($this->selectedIndex >= count($this->lines)) {
                        $this->selectedIndex = max(0, count($this->lines) - 1);
                    }
                }
            }
            // Guardar y salir
            elseif ($key === 'F10') {
                if (!empty($this->lines)) {
                    return $this->lines;
                }
            }
            // Cancelar
            elseif ($key === 'F12' || $key === 'ESC') {
                return null;
            }
        }
    }
    
    /**
     * Renderiza el editor
     */
    private function render(): void
    {
        $this->screen->clear();
        
        // Borde superior
        echo "\033[36m";
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título
        $titleLength = mb_strlen($this->title);
        $availableSpace = $this->width - 4;
        $totalPadding = $availableSpace - $titleLength;
        $leftPadding = (int)floor($totalPadding / 2) + 1;
        $rightPadding = (int)ceil($totalPadding / 2) + 1;
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $this->title . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n\n";
        
        if (empty($this->lines)) {
            echo "  \033[33mNo hay líneas. Presione F5 para añadir.\033[0m\n\n";
        } else {
            // Cabecera de tabla
            echo "  \033[36m";
            echo str_pad("Producto", 35);
            echo str_pad("Cant.", 10, " ", STR_PAD_LEFT);
            echo str_pad("Precio", 12, " ", STR_PAD_LEFT);
            echo str_pad("Desc%", 8, " ", STR_PAD_LEFT);
            echo str_pad("Total", 12, " ", STR_PAD_LEFT);
            echo "\033[0m\n";
            echo "  \033[36m" . str_repeat("─", 77) . "\033[0m\n";
            
            // Líneas
            $totalGeneral = 0;
            foreach ($this->lines as $index => $line) {
                $isSelected = ($index === $this->selectedIndex);
                $prefix = $isSelected ? "\033[1;33m► " : "  ";
                $color = $isSelected ? "\033[1;33m" : "\033[37m";
                
                $cantidad = $line['cantidad'] ?? 0;
                $precio = $line['precio_unitario'] ?? 0;
                $descuento = $line['descuento'] ?? 0;
                
                $subtotal = $cantidad * $precio;
                $total = $subtotal * (1 - $descuento / 100);
                $totalGeneral += $total;
                
                echo $prefix . $color;
                echo str_pad(substr($line['descripcion'] ?? '', 0, 33), 35);
                echo str_pad(number_format($cantidad, 2, ',', '.'), 10, " ", STR_PAD_LEFT);
                echo str_pad(number_format($precio, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
                echo str_pad(number_format($descuento, 0) . '%', 8, " ", STR_PAD_LEFT);
                echo str_pad(number_format($total, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
                echo "\033[0m\n";
            }
            
            // Total
            echo "  \033[36m" . str_repeat("─", 77) . "\033[0m\n";
            echo "  \033[1;32m" . str_pad("TOTAL:", 65, " ", STR_PAD_LEFT);
            echo str_pad(number_format($totalGeneral, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
            echo "\033[0m\n";
        }
        
        echo "\n";
        
        // Barra de funciones
        echo "\033[36m";
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        echo "║ \033[0m";
        
        $functions = [
            "\033[32mF5\033[0m=Añadir",
            "\033[32mF2/F6\033[0m=Editar",
            "\033[32mF8\033[0m=Eliminar",
            "\033[37m↑↓\033[0m=Navegar",
            "\033[32mF10\033[0m=Guardar",
            "\033[32mF12\033[0m=Cancelar"
        ];
        
        $functionText = implode("  ", $functions);
        $padding = $this->width - 4 - $this->stripAnsiLength($functionText);
        
        echo $functionText . str_repeat(" ", max(0, $padding));
        echo "\033[36m ║\n";
        echo "╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n";
    }
    
    /**
     * Edita la línea seleccionada
     */
    private function editLine(callable $searchCallback): void
    {
        $line = $this->lines[$this->selectedIndex];
        
        // Crear un modal prellenado con los datos actuales
        $form = new FormController($this->keyHandler, $this->screen, 'EDITAR LÍNEA');
        
        $form->addField('producto', 'Producto', $line['descripcion'] ?? '', readonly: true)
             ->addField('cantidad', 'Cantidad', (string)($line['cantidad'] ?? 1), required: true, validator: function($value) {
                 if (!is_numeric(str_replace(',', '.', $value))) {
                     return 'Debe ser numérico';
                 }
                 return null;
             })
             ->addField('precio', 'Precio Unit.', number_format($line['precio_unitario'] ?? 0, 2, ',', '.'), readonly: true)
             ->addField('descuento', 'Descuento %', (string)($line['descuento'] ?? 0), required: false, validator: function($value) {
                 $num = (float)str_replace(',', '.', $value);
                 if ($num < 0 || $num > 100) {
                     return 'Debe estar entre 0 y 100';
                 }
                 return null;
             });
        
        $values = $form->run();
        
        if ($values) {
            // Actualizar la línea
            $this->lines[$this->selectedIndex]['cantidad'] = (float)str_replace(',', '.', $values['cantidad']);
            $this->lines[$this->selectedIndex]['descuento'] = (float)str_replace(',', '.', $values['descuento']);
        }
    }
    
    /**
     * Confirma la eliminación de una línea
     */
    private function confirmDelete(): bool
    {
        $this->screen->clear();
        echo "\n\n";
        echo "  \033[1;31m╔════════════════════════════════════════════════════════════╗\033[0m\n";
        echo "  \033[1;31m║              CONFIRMAR ELIMINACIÓN DE LÍNEA                ║\033[0m\n";
        echo "  \033[1;31m╚════════════════════════════════════════════════════════════╝\033[0m\n\n";
        echo "  \033[37m¿Está seguro de que desea eliminar esta línea?\033[0m\n\n";
        echo "  \033[33mF10\033[0m = Confirmar    \033[33mF12\033[0m = Cancelar\n\n";
        
        while (true) {
            $rawKey = $this->keyHandler->waitForKey();
            $key = FunctionKeyMapper::mapKey($rawKey);
            
            if ($key === 'F10') {
                return true;
            } elseif ($key === 'F12' || $key === 'ESC') {
                return false;
            }
        }
    }
    
    /**
     * Calcula longitud sin códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    }
}
