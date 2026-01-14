<?php

namespace App\NexErpTui\Display;

use App\NexErpTui\Input\KeyHandler;
use App\NexErpTui\Input\FunctionKeyMapper;

/**
 * ListController - Controlador de listas/tablas tipo AS/400
 * 
 * Maneja la navegación con teclas de cursor en listas de datos,
 * paginación y acciones sobre registros seleccionados
 */
class ListController
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private string $title;
    private array $columns = [];
    private array $data = [];
    private int $selectedRow = 0;
    private int $page = 1;
    private int $perPage = 15;
    private int $totalRecords = 0;
    private int $totalPages = 1;
    private int $width;
    
    // Callbacks para acciones
    private mixed $onFetch = null;
    private mixed $onCreate = null;
    private mixed $onEdit = null;
    private mixed $onDelete = null;
    private mixed $onView = null;
    
    // Estado
    private bool $isRunning = true;
    private ?string $action = null;
    
    private FullScreenLayout $layout;
    
    public function __construct(KeyHandler $keyHandler, Screen $screen, string $title, int $width = 80)
    {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->title = $title;
        $this->width = $width;
        $this->layout = new FullScreenLayout($screen);
        $this->layout->setTitle($title);
    }
    
    /**
     * Define las columnas de la tabla
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Establece el callback para obtener datos
     */
    public function onFetch(callable $callback): self
    {
        $this->onFetch = $callback;
        return $this;
    }
    
    /**
     * Establece el callback para crear registro
     */
    public function onCreate(callable $callback): self
    {
        $this->onCreate = $callback;
        return $this;
    }
    
    /**
     * Establece el callback para editar registro
     */
    public function onEdit(callable $callback): self
    {
        $this->onEdit = $callback;
        return $this;
    }
    
    /**
     * Establece el callback para eliminar registro
     */
    public function onDelete(callable $callback): self
    {
        $this->onDelete = $callback;
        return $this;
    }
    
    /**
     * Establece el callback para ver detalles
     */
    public function onView(callable $callback): self
    {
        $this->onView = $callback;
        return $this;
    }
    
    /**
     * Ejecuta el controlador de lista
     */
    public function run(): void
    {
        $this->keyHandler->setRawMode(true);
        $this->isRunning = true;
        $needsFetch = true;
        
        while ($this->isRunning) {
            if ($needsFetch) {
                $this->fetchData();
                $needsFetch = false;
            }
            
            $this->render();
            $needsFetch = $this->handleInput();
        }
    }
    
    /**
     * Obtiene los datos usando el callback
     */
    private function fetchData(): void
    {
        if (!$this->onFetch) {
            return;
        }
        
        $result = call_user_func($this->onFetch, $this->page, $this->perPage);
        
        $this->data = $result['data'] ?? [];
        $this->totalRecords = $result['total'] ?? 0;
        $this->totalPages = $result['last_page'] ?? 1;
        
        // Ajustar selección si está fuera de rango
        if ($this->selectedRow >= count($this->data)) {
            $this->selectedRow = max(0, count($this->data) - 1);
        }
    }
    
    /**
     * Renderiza la lista completa de forma responsiva dentro del layout
     */
    public function render(int $width = 0, int $height = 0): void
    {
        // En este contexto, render() es llamado por el bucle principal run().
        // Delegamos al layout la gestión de la pantalla completa.
        
        $this->layout->setTitle($this->title); // Actualizar título por si cambia
        
        // Configurar menú en el layout (opcional, si queremos ver el menú mientras navegamos la lista)
        // Por ahora, solo título y contenido
        
        $this->layout->render(function($w, $h) {
            $this->renderTableInLayout($w, $h);
        });
    }

    /**
     * Renderiza la tabla dentro del área asignada por el layout
     */
    private function renderTableInLayout(int $width, int $height): void
    {
        $this->width = $width;
        
        // Calcular distribución de columnas dinámica
        // ... (resto del código de cálculo de columnas igual)
        
        $effectiveWidth = $this->width; // Sin bordes extra, el layout ya pone bordes
        $fixedWidth = 0;
        $flexColumns = [];
        
        foreach ($this->columns as $key => $col) {
            $isFlex = $col['flex'] ?? false;
            if (!$isFlex && in_array($key, ['cliente', 'nombre', 'descripcion', 'tercero.nombre_comercial'])) {
                $isFlex = true;
            }
            // ... (resto igual)
            if ($isFlex) $flexColumns[] = $key;
            else $fixedWidth += ($col['width'] ?? 15);
        }
        
        // Ajuste: si width es muy pequeño, evitar error
        if ($effectiveWidth < 20) $effectiveWidth = 20;

        $availableFlexWidth = max(10, $effectiveWidth - $fixedWidth);
        
        // ... (cálculo de renderColumns igual)
        $renderColumns = $this->columns;
        if (count($flexColumns) > 0) {
            $widthPerFlex = (int)floor($availableFlexWidth / count($flexColumns));
            foreach ($flexColumns as $key) {
               // ... actualizar width
               // Necesitamos reiterar el array original para encontrar la referencia correcta
               // Soporte simple para array asociativo:
               if (isset($renderColumns[$key])) {
                   $renderColumns[$key]['width'] = $widthPerFlex;
               } else {
                   // Búsqueda lineal
                   foreach($renderColumns as $idx => $c) {
                        if (($c['field']??'') === $key) {
                            $renderColumns[$idx]['width'] = $widthPerFlex;
                        }
                   }
               }
            }
        } else {
             // Estirar última columna
            $keys = array_keys($renderColumns);
            $lastKey = end($keys);
            // Cuidado con array_sum, usa las columnas originales, no las renderColumns
            // Mejor recalcular
            $currentTotal = 0;
            foreach ($renderColumns as $c) $currentTotal += $c['width'];
            
            $renderColumns[$lastKey]['width'] += ($effectiveWidth - $currentTotal);
        }

        // Renderizado visual de la tabla
        
        // Título de tabla y Paginación en una línea compacta
        $pagingInfo = "Pág {$this->page}/{$this->totalPages} | Total: {$this->totalRecords}";
        // Alienar: Título a la izquierda, Paging a la derecha
        $titleStr = mb_substr($this->title, 0, 40);
        // Restamos 2 para dar margen y evitar que pegue al borde derecho
        $space = $effectiveWidth - mb_strlen($titleStr) - mb_strlen($pagingInfo) - 2;
        
        echo "\n \033[1;37m" . $titleStr . str_repeat(" ", max(1, $space)) . "\033[33m" . $pagingInfo . "\033[0m\n";
        
        // Línea separadora
        echo "\033[36m" . str_repeat("─", $effectiveWidth) . "\033[0m\n";
        
        // Cabeceras
        foreach ($renderColumns as $col) {
            $align = $col['align'] ?? 'left';
            $padType = ($align === 'right') ? STR_PAD_LEFT : STR_PAD_RIGHT;
            // Asegurar que el ancho es válido > 0
            $w = max(1, $col['width']);
            // Truncar label si excede width
            $lbl = mb_substr($col['label'], 0, $w);
            echo "\033[36m" . mb_str_pad($lbl, $w, " ", $padType);
        }
        echo "\033[0m\n";
        
        echo "\033[36m" . str_repeat("─", $effectiveWidth) . "\033[0m\n";
        
        // Datos
        if (empty($this->data)) {
            echo "\n  \033[33mNo hay registros para mostrar\033[0m\n";
        } else {
            foreach ($this->data as $rowIndex => $row) {
                $isSelected = ($rowIndex === $this->selectedRow);
                $color = $isSelected ? "\033[1;33m" : "\033[37m";
                // Nota: el prefijo empuja todo 2 caracteres. Debemos restar 2 al width total o manejarlo.
                // Simple: Robamos 2 caracteres a la primera columna o los ignoramos (confiando en overflow handling del layout)
                // Mejor: ListController debería dibujar EXACTO width.
                // Ajuste: Reducir effectiveWidth en 2 al principio, o incluirlos en la primera columna.
                
                // Vamos a simplificar: El indicador de selección va "sobre" el margen izquierdo o se asume.
                // La versión anterior usaba "► " y descolocaba si no se calculaba.
                
                // Opción B: No usar prefijo extra, sino colorear la línea completa.
                // Opción A: Usar prefijo en la primera columna.
                echo $color;
                
                $first = true;
                foreach ($renderColumns as $key => $col) {
                    $field = $col['field'] ?? $key;
                    $w = max(1, $col['width']);
                    $align = $col['align'] ?? 'left';
                    
                    if (isset($col['formatter']) && is_callable($col['formatter'])) {
                         $value = call_user_func($col['formatter'], $row);
                    } else {
                         $value = $this->getNestedValue($row, $field);
                         if (isset($col['format'])) $value = $this->formatValue($value, $col['format']);
                    }
                    $value = (string)$value;
                    
                    $displayVal = $value;
                    if ($first) {
                         // Añadir marca selección
                         if ($isSelected) $displayVal = "►" . substr($value, 0); 
                         else $displayVal = " " . substr($value, 0);
                         $first = false;
                    }
                    
                    // Truncar
                    if (mb_strlen($displayVal) > $w) $displayVal = mb_substr($displayVal, 0, $w-1) . '…';
                    
                    $padType = ($align === 'right') ? STR_PAD_LEFT : STR_PAD_RIGHT;
                    echo mb_str_pad($displayVal, $w, " ", $padType);
                }
                echo "\033[0m\n";
            }
        }
        
        // Barra funciones (renderizada por el layout al final, o aquí al pie)
        // El layout tiene status bar. Podemos poner info extra ahí.
        // O dibujarla al final del contenido.
        echo "\n\033[36m" . str_repeat("─", $effectiveWidth) . "\033[0m\n";
        echo $this->getFunctionBarText();
    }
    
    private function getFunctionBarText(): string {
        $functions = [];
        if ($this->onEdit) $functions[] = "F2=Editar";
        if ($this->onCreate) $functions[] = "F5=Crear";
        if ($this->onDelete) $functions[] = "F8=Eliminar";
        if ($this->onView) $functions[] = "Enter=Ver";
        $functions[] = "F12=Volver";
        return "\033[32m" . implode("  ", $functions) . "\033[0m";
    }
    
    // Helper para str_pad con multibyte (no nativo en PHP < 8.3)
    // Si no existe, lo definimos fuera o simulamos
    private function mb_str_pad($input, $pad_length, $pad_string = " ", $pad_type = STR_PAD_RIGHT) {
        return str_pad($input, strlen($input) - mb_strlen($input) + $pad_length, $pad_string, $pad_type);
    }
    
    /**
     * Obtiene un valor anidado de un array (ej: 'user.name')
     */
    private function getNestedValue(array $data, string $field): string
    {
        $keys = explode('.', $field);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return '';
            }
        }
        
        return (string)$value;
    }
    
    /**
     * Formatea valores según tipo
     */
    private function formatValue($value, string $format): string
    {
        return match($format) {
            'currency' => $this->formatCurrency((float)$value),
            'percentage' => str_pad($value . '%', 6, ' ', STR_PAD_LEFT),
            'date' => $value ? date('d/m/Y', strtotime($value)) : '',
            'datetime' => $value ? date('d/m/Y H:i', strtotime($value)) : '',
            'boolean' => $value ? 'Sí' : 'No',
            'number' => $this->formatNumber((float)$value),
            default => (string)$value
        };
    }
    
    /**
     * Formatea moneda con alineación decimal
     */
    private function formatCurrency(float $value): string
    {
        // Formato: "1.234,56 €" alineado a la derecha
        return str_pad(number_format($value, 2, ',', '.') . ' €', 12, ' ', STR_PAD_LEFT);
    }
    
    /**
     * Formatea número con alineación decimal
     */
    private function formatNumber(float $value, int $decimals = 2): string
    {
        return str_pad(number_format($value, $decimals, ',', '.'), 10, ' ', STR_PAD_LEFT);
    }
    
    /**
     * Renderiza la barra de funciones
     */
    private function renderFunctionBar(): void
    {
        echo "\033[36m";
        echo "╠" . str_repeat("═", $this->width - 2) . "╣\n";
        echo "║ \033[0m";
        
        $functions = [];
        
        if ($this->onCreate) {
            $functions[] = "\033[32mF5\033[0m=Crear";
        }
        if ($this->onEdit) {
            $functions[] = "\033[32mF2\033[0m/\033[32mF6\033[0m=Editar";
        }
        if ($this->onDelete) {
            $functions[] = "\033[32mF8\033[0m=Eliminar";
        }
        if ($this->onView) {
            $functions[] = "\033[37mEnter\033[0m=Ver";
        }
        
        $functions[] = "\033[37m↑↓\033[0m=Navegar";
        $functions[] = "\033[32mF12\033[0m=Volver";
        
        $functionText = implode("  ", $functions);
        $padding = $this->width - 4 - $this->stripAnsiLength($functionText);
        
        echo $functionText . str_repeat(" ", max(0, $padding));
        echo "\033[36m ║\n";
        echo "╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n";
    }
    
    /**
     * Maneja la entrada del usuario
     * Retorna true si necesita refrescar datos
     */
    private function handleInput(): bool
    {
        $rawKey = $this->keyHandler->waitForKey();
        $key = FunctionKeyMapper::mapKey($rawKey);
        
        // Navegación con cursores
        if ($key === 'UP') {
            if ($this->selectedRow > 0) {
                $this->selectedRow--;
            } elseif ($this->page > 1) {
                // Ir a página anterior
                $this->page--;
                return true; // Necesita refetch
            }
        } elseif ($key === 'DOWN') {
            if ($this->selectedRow < count($this->data) - 1) {
                $this->selectedRow++;
            } elseif ($this->page < $this->totalPages) {
                // Ir a página siguiente
                $this->page++;
                $this->selectedRow = 0;
                return true; // Necesita refetch
            }
        }
        // Paginación
        elseif ($key === 'PAGE_UP') {
            if ($this->page > 1) {
                $this->page--;
                $this->selectedRow = 0;
                return true;
            }
        } elseif ($key === 'PAGE_DOWN') {
            if ($this->page < $this->totalPages) {
                $this->page++;
                $this->selectedRow = 0;
                return true;
            }
        }
        // Acciones
        elseif ($key === 'F5' && $this->onCreate) {
            // Crear
            call_user_func($this->onCreate);
            return true; // Refrescar después de crear
        } elseif (($key === 'F2' || $key === 'F6') && $this->onEdit) {
            // Editar
            $selected = $this->getSelectedRecord();
            if ($selected) {
                call_user_func($this->onEdit, $selected);
                return true; // Refrescar después de editar
            }
        } elseif ($key === 'F8' && $this->onDelete) {
            // Eliminar
            $selected = $this->getSelectedRecord();
            if ($selected && $this->confirmDelete()) {
                call_user_func($this->onDelete, $selected);
                return true; // Refrescar después de eliminar
            }
        } elseif ($key === 'ENTER' && $this->onView) {
            // Ver detalles
            $selected = $this->getSelectedRecord();
            if ($selected) {
                call_user_func($this->onView, $selected);
            }
        } elseif ($key === 'F12' || $key === 'ESC') {
            // Volver
            $this->isRunning = false;
        }
        
        return false; // No necesita refetch
    }
    
    /**
     * Obtiene el registro seleccionado
     */
    private function getSelectedRecord(): ?array
    {
        return $this->data[$this->selectedRow] ?? null;
    }
    
    /**
     * Confirma la eliminación
     */
    private function confirmDelete(): bool
    {
        $this->screen->clear();
        echo "\n\n";
        echo "  \033[1;31m╔════════════════════════════════════════════════════════════╗\033[0m\n";
        echo "  \033[1;31m║                    CONFIRMAR ELIMINACIÓN                   ║\033[0m\n";
        echo "  \033[1;31m╚════════════════════════════════════════════════════════════╝\033[0m\n\n";
        echo "  \033[37m¿Está seguro de que desea eliminar este registro?\033[0m\n\n";
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
