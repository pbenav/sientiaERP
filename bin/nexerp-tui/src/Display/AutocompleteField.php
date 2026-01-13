<?php

namespace App\NexErpTui\Display;

use App\NexErpTui\Input\KeyHandler;
use App\NexErpTui\Input\FunctionKeyMapper;

/**
 * AutocompleteField - Campo de texto con autocompletado en tiempo real
 * 
 * Permite búsqueda incremental mientras el usuario escribe
 */
class AutocompleteField
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private string $title;
    private string $placeholder;
    private array $items = [];
    private string $searchText = '';
    private int $selectedIndex = 0;
    private int $width;
    
    public function __construct(
        KeyHandler $keyHandler,
        Screen $screen,
        string $title,
        string $placeholder = 'Buscar...',
        int $width = 80
    ) {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->title = $title;
        $this->placeholder = $placeholder;
        $this->width = $width;
    }
    
    /**
     * Ejecuta el campo de autocompletado
     * 
     * @param callable $searchCallback Función que recibe el texto de búsqueda y devuelve array de items
     * @return array|null Item seleccionado o null si se canceló
     */
    public function run(callable $searchCallback): ?array
    {
        // Resetear estado al inicio
        $this->searchText = '';
        $this->items = [];
        $this->selectedIndex = 0;
        
        $this->keyHandler->setRawMode(true);
        
        while (true) {
            // Buscar items según el texto actual
            if (!empty($this->searchText)) {
                $this->items = call_user_func($searchCallback, $this->searchText);
            } else {
                $this->items = [];
            }
            
            // Ajustar índice seleccionado
            if ($this->selectedIndex >= count($this->items)) {
                $this->selectedIndex = max(0, count($this->items) - 1);
            }
            
            $this->render();
            
            $rawKey = $this->keyHandler->waitForKey();
            $key = FunctionKeyMapper::mapKey($rawKey);
            
            // Navegación
            if ($key === 'UP') {
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                }
            } elseif ($key === 'DOWN') {
                if ($this->selectedIndex < count($this->items) - 1) {
                    $this->selectedIndex++;
                }
            }
            // Selección
            elseif ($key === 'ENTER') {
                if (!empty($this->items) && isset($this->items[$this->selectedIndex])) {
                    return $this->items[$this->selectedIndex];
                }
            }
            // Cancelar
            elseif ($key === 'ESC' || $key === 'F12') {
                return null;
            }
            // Borrar
            elseif ($key === 'BACKSPACE' || $key === 'DELETE') {
                if (mb_strlen($this->searchText) > 0) {
                    $this->searchText = mb_substr($this->searchText, 0, -1);
                    $this->selectedIndex = 0;
                }
            }
            // Escribir
            elseif (strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
                $this->searchText .= $key;
                $this->selectedIndex = 0;
            }
        }
    }
    
    /**
     * Renderiza el campo de autocompletado
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
        
        if ($titleLength > $availableSpace) {
            $displayTitle = mb_substr($this->title, 0, $availableSpace);
            $leftPadding = 1;
            $rightPadding = 1;
        } else {
            $displayTitle = $this->title;
            $totalPadding = $availableSpace - $titleLength;
            $leftPadding = (int)floor($totalPadding / 2) + 1;
            $rightPadding = (int)ceil($totalPadding / 2) + 1;
        }
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $displayTitle . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n\n";
        
        // Campo de búsqueda
        echo "  \033[37mBuscar: \033[1;33m" . $this->searchText . "_\033[0m\n";
        
        if (empty($this->searchText)) {
            echo "  \033[90m(" . $this->placeholder . ")\033[0m\n\n";
        } else {
            echo "\n";
        }
        
        // Resultados
        if (empty($this->items)) {
            if (!empty($this->searchText)) {
                echo "  \033[33mNo se encontraron resultados\033[0m\n\n";
            }
        } else {
            echo "  \033[36m" . str_repeat("─", $this->width - 4) . "\033[0m\n";
            
            // Mostrar hasta 10 resultados
            $maxResults = min(10, count($this->items));
            for ($i = 0; $i < $maxResults; $i++) {
                $item = $this->items[$i];
                $isSelected = ($i === $this->selectedIndex);
                
                $prefix = $isSelected ? "\033[1;33m► " : "  ";
                $color = $isSelected ? "\033[1;33m" : "\033[37m";
                
                // Formato: Código - Nombre - Precio
                $display = $this->formatItem($item);
                
                echo $prefix . $color . $display . "\033[0m\n";
            }
            
            if (count($this->items) > 10) {
                echo "\n  \033[90m... y " . (count($this->items) - 10) . " más\033[0m\n";
            }
            
            echo "  \033[36m" . str_repeat("─", $this->width - 4) . "\033[0m\n";
        }
        
        // Ayuda
        echo "\n  \033[37m↑↓\033[0m=Navegar  \033[37mEnter\033[0m=Seleccionar  ";
        echo "\033[32mF12/ESC\033[0m=Cancelar\n";
    }
    
    /**
     * Formatea un item para mostrar
     */
    private function formatItem(array $item): string
    {
        $sku = str_pad($item['sku'] ?? '', 15);
        $name = str_pad(substr($item['name'] ?? '', 0, 35), 37);
        $price = str_pad(number_format($item['price'] ?? 0, 2, ',', '.') . ' €', 12, ' ', STR_PAD_LEFT);
        
        return $sku . $name . $price;
    }
}
