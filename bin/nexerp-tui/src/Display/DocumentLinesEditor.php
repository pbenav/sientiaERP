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
        
        // --- CABECERA ---
        echo "\033[36m";
        echo "╔" . str_repeat("═", $this->width - 2) . "╗\n";
        
        // Título centrado
        $titleLength = mb_strlen($this->title);
        $availableSpace = $this->width - 2; // -2 bordes (║...║)
        $totalPadding = max(0, $availableSpace - $titleLength);
        $leftPadding = (int)floor($totalPadding / 2);
        $rightPadding = (int)ceil($totalPadding / 2);
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $this->title . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n"; // Separador cabecera
        
        // --- CUERPO ---
        $contentLines = [];
        
        if (empty($this->lines)) {
            $contentLines[] = "";
            $contentLines[] = "  \033[33mNo hay líneas añadidas todavía\033[0m";
            $contentLines[] = "";
            $contentLines[] = "  Presione \033[32mF5\033[0m para añadir línea, \033[32mF10\033[0m para finalizar, \033[32mF12\033[0m para cancelar";
        } else {
            // ... (código existente de tabla) ...
            $header = "  \033[36m" . 
                str_pad("Producto", 35) .
                str_pad("Cant.", 10, " ", STR_PAD_LEFT) .
                str_pad("Precio", 12, " ", STR_PAD_LEFT) .
                str_pad("Desc%", 8, " ", STR_PAD_LEFT) .
                str_pad("Total", 12, " ", STR_PAD_LEFT) . 
                "\033[0m";
            
            $contentLines[] = $header;
            $contentLines[] = "  \033[36m" . str_repeat("─", $this->width - 6) . "\033[0m";
            
            $totalGeneral = 0;
            foreach ($this->lines as $index => $line) {
                // ... (cálculos existentes) ...
                $cantidad = $line['cantidad'] ?? 0;
                $precio = $line['precio_unitario'] ?? 0;
                $descuento = $line['descuento'] ?? 0;
                $subtotal = $cantidad * $precio;
                $total = $subtotal * (1 - $descuento / 100);
                $totalGeneral += $total;

                $isSelected = ($index === $this->selectedIndex);
                $prefix = $isSelected ? "\033[1;33m► " : "  ";
                $color = $isSelected ? "\033[1;33m" : "\033[37m";
                $reset = "\033[0m";

                $lineStr = $prefix . $color .
                    str_pad(substr($line['descripcion'] ?? '', 0, 33), 35) .
                    str_pad(number_format($cantidad, 2, ',', '.'), 10, " ", STR_PAD_LEFT) .
                    str_pad(number_format($precio, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT) .
                    str_pad(number_format($descuento, 0) . '%', 8, " ", STR_PAD_LEFT) .
                    str_pad(number_format($total, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT) .
                    $reset;
                
                $contentLines[] = $lineStr;
            }
            
            $contentLines[] = "  \033[36m" . str_repeat("─", $this->width - 6) . "\033[0m";
            
            $totalLabel = "TOTAL:";
            $totalValue = number_format($totalGeneral, 2, ',', '.') . ' €';
            $padLen = ($this->width - 6) - mb_strlen($totalLabel) - mb_strlen($totalValue) - 2; 
            if ( $padLen < 0 ) $padLen = 0;

            $contentLines[] = "  \033[1;32m" . $totalLabel . str_repeat(" ", $padLen) . $totalValue . "\033[0m";
        }
        
        // Rellenar hasta altura mínima para formar un marco decente
        $minHeight = 15;
        while (count($contentLines) < $minHeight) {
            $contentLines[] = "";
        }
        
        // Imprimir líneas del cuerpo con bordes laterales
        foreach ($contentLines as $line) {
            $visibleLen = $this->stripAnsiLength($line);
            $paddingRight = max(0, $this->width - 2 - $visibleLen);
            
            echo "\033[36m║\033[0m" . $line . str_repeat(" ", $paddingRight) . "\033[36m║\033[0m\n";
        }

        // Rellenar líneas vacías si es muy corto para mantener estética (opcional, por ahora dinámico)
        
        // --- PIE DE PÁGINA ---
        echo "\033[36m╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n"; // Separador pie
        
        echo "\033[36m║ \033[0m";
        
        // Reordenación de teclas: F2, F5, F8, F10, F12
        $functions = [
            "\033[32mF2\033[0m=Editar",
            "\033[32mF5\033[0m=Añadir", 
            "\033[32mF8\033[0m=Eliminar",
            // "\033[37mTAB\033[0m=Cabecera", // Si queremos mantener lo que había en screenshot
            "\033[32mF10\033[0m=Guardar",
            "\033[32mF12\033[0m=Cancelar"
        ];
        
        $functionText = implode("  ", $functions);
        $len = $this->stripAnsiLength($functionText);
        $padding = max(0, $this->width - 4 - $len);
        
        echo $functionText . str_repeat(" ", $padding);
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
