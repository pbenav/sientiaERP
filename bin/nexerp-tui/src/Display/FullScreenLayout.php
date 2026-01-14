<?php

namespace App\NexErpTui\Display;

/**
 * FullScreenLayout - Layout de pantalla completa tipo GUI
 * 
 * Gestiona una interfaz completa con:
 * - Cabecera superior (empresa, título, fecha/hora)
 * - Menú horizontal navegable
 * - Área de trabajo
 * - Barra de estado inferior
 */
class FullScreenLayout
{
    private Screen $screen;
    private int $width;
    private int $height;
    
    // Configuración
    private string $companyName = 'nexERP';
    private string $currentTitle = '';
    private array $menuItems = [];
    private int $selectedMenuItem = 0;
    private array $subMenuItems = [];
    private int $selectedSubMenuItem = -1;
    
    // Áreas de la pantalla
    private const HEADER_HEIGHT = 1;
    private const MENU_HEIGHT_SINGLE = 2; // Solo menú principal
    private const MENU_HEIGHT_DOUBLE = 3; // Menú + Submenú (sin separador intermedio) + Separador final
    private const STATUS_HEIGHT = 1; // Solo el contenido, el separador cuenta aparte
    
    public function __construct(Screen $screen)
    {
        $this->screen = $screen;
        $this->updateDimensions();
    }
    
    private function updateDimensions(): void
    {
        $cols = (int)shell_exec('tput cols');
        $lines = (int)shell_exec('tput lines');
        $this->width = $cols > 0 ? $cols : 80;
        $this->height = $lines > 0 ? $lines : 24;
    }
    /**
     * Renderiza el borde superior
     */
    private function renderTopBorder(): void
    {
        echo "\033[36m╔" . str_repeat("═", $this->width - 2) . "╗\033[0m\n";
    }

    /**
     * Renderiza la cabecera (Empresa, Título, Fecha)
     */
    private function renderHeader(): void
    {
        $date = date('d/m/Y H:i');
        
        // Calcular longitudes visuales (usando mb_strwidth)
        $compLen = mb_strwidth($this->companyName);
        $title = mb_strtoupper($this->currentTitle);
        $titleLen = mb_strwidth($title);
        $dateLen = mb_strwidth($date);
        
        // Espacio interior disponible (ancho - 4 por los bordes '║ ' y ' ║')
        $innerSpace = $this->width - 4;
        
        // Calcular espacios para centrar el título
        // Posición ideal de inicio del título
        $startTitle = (int) floor(($innerSpace - $titleLen) / 2);
        
        // Espacios desde el final de la empresa hasta el inicio del título
        $spaces1 = $startTitle - $compLen;
        
        // Asegurar mínimo 1 espacio de separación
        if ($spaces1 < 1) $spaces1 = 1;
        
        // Espacios desde el final del título hasta el inicio de la fecha
        $currentPos = $compLen + $spaces1 + $titleLen;
        $datePos = $innerSpace - $dateLen;
        $spaces2 = $datePos - $currentPos;
        
        // Asegurar mínimo 1 espacio
        if ($spaces2 < 1) $spaces2 = 1;

        // Verificar si nos pasamos del ancho (puede pasar en terminales muy estrechas)
        $totalLen = $compLen + $spaces1 + $titleLen + $spaces2 + $dateLen;
        if ($totalLen > $innerSpace) {
            // Si nos pasamos, reducimos espacios
            $excess = $totalLen - $innerSpace;
            if ($spaces2 > $excess + 1) {
                $spaces2 -= $excess;
            } elseif ($spaces1 > $excess + 1) {
                $spaces1 -= $excess;
            }
        }

        // Renderizar línea única
        echo "\033[36m║\033[0m ";
        echo "\033[1;36m" . $this->companyName . "\033[0m";
        echo str_repeat(" ", (int)$spaces1);
        echo "\033[1;33m" . $title . "\033[0m";
        echo str_repeat(" ", (int)$spaces2);
        echo "\033[1;37m" . $date . "\033[0m";
        echo " \033[36m║\033[0m\n";
    }
    
    /**
     * Renderiza un separador horizontal
     */
    private function renderSeparator(): void
    {
        echo "\033[36m╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n";
    }

    /**
     * Configura el nombre de la empresa
     */
    public function setCompanyName(string $name): self
    {
        $this->companyName = $name;
        return $this;
    }

    /**
     * Configura el título de la pantalla
     */
    public function setTitle(string $title): self
    {
        $this->currentTitle = $title;
        return $this;
    }

    /**
     * Configura los items del menú principal
     */
    public function setMenuItems(array $items): self
    {
        $this->menuItems = $items;
        return $this;
    }

    /**
     * Configura el item de menú seleccionado
     */
    public function setSelectedMenuItem(int $index): self
    {
        $this->selectedMenuItem = $index;
        return $this;
    }

    /**
     * Configura los items del submenú
     */
    public function setSubMenuItems(array $items): self
    {
        $this->subMenuItems = $items;
        return $this;
    }
    
    /**
     * Configura el item de submenú seleccionado
     */
    public function setSelectedSubMenuItem(int $index): self
    {
        $this->selectedSubMenuItem = $index;
        return $this;
    }
    
    /**
     * Renderiza el layout completo
     */
    public function render(callable $contentRenderer): void
    {
        // Actualizar dimensiones por si la terminal cambió de tamaño
        $this->updateDimensions();
        
        $this->screen->clear();
        
        // 1. Borde superior (1 línea)
        $this->renderTopBorder();
        
        // 2. Cabecera (1 línea)
        $this->renderHeader();
        
        // 3. Separador (1 línea)
        $this->renderSeparator();
        
        // 4. Menú horizontal (1 línea)
        $this->renderMenu();
        
        // 5. Submenú horizontal (1 línea, si existe)
        // NOTA: Eliminamos el separador intermedio para ahorrar espacio
        $hasSubMenu = !empty($this->subMenuItems);
        if ($hasSubMenu) {
            $this->renderSubMenu();
        }
        
        // 6. Separador de área de trabajo (1 línea)
        $this->renderSeparator();
        
        // Cálculo preciso de líneas usadas
        $fixedLines = 1 + 1 + 1 + 1 + ($hasSubMenu ? 1 : 0) + 1 + 2 + 1;
        
        $workAreaHeight = $this->height - $fixedLines;
        if ($workAreaHeight < 5) $workAreaHeight = 5; // Mínimo de seguridad

        $this->renderWorkArea($contentRenderer, $workAreaHeight);
        
        // Barra de estado
        $this->renderStatusBar();
        
        // Borde inferior
        $this->renderBottomBorder();
    }

    /**
     * Renderiza el menú horizontal
     */
    private function renderMenu(): void
    {
        echo "\033[36m║\033[0m ";
        
        $menuText = '';
        $menuVisualLength = 0;
        
        foreach ($this->menuItems as $index => $item) {
            $isSelected = ($index === $this->selectedMenuItem);
            
            if ($isSelected) {
                // Destacado: ► (width 2? often 1, but let's be safe visually)
                // Usamos mb_strwidth para contar ancho visual real
                $label = mb_strtoupper($item);
                $arrow = "►"; 
                // NOTA: Algunos terminales renderizan ► como 1 char, otros como 2. 
                // mb_strwidth('►') suele devolver 1, pero si visualmente ocupa más...
                // Asumiremos que los símbolos y texto están bien medidos por mb_strwidth.
                
                $menuText .= "\033[1;33m{$arrow} {$label} \033[0m  ";
                $menuVisualLength += mb_strwidth($arrow) + 1 + mb_strwidth($label) + 3; // +espacio + espacio + espacio
            } else {
                $menuText .= "\033[37m  " . $item . " \033[0m  ";
                $menuVisualLength += 2 + mb_strwidth($item) + 3; // espacios + espacios
            }
        }
        
        // Rellenar
        $availableSpace = $this->width - 4;
        $padding = $availableSpace - $menuVisualLength;
        
        echo $menuText;
        echo str_repeat(" ", max(0, $padding));
        echo " \033[36m║\033[0m\n";
    }

    /**
     * Renderiza el submenú horizontal
     */
    private function renderSubMenu(): void
    {
        echo "\033[36m║\033[0m "; // Indentación visual para submenú
        
        $menuText = '';
        $menuVisualLength = 0;
        
        foreach ($this->subMenuItems as $index => $item) {
            // Manejamos claves de array si es asociativo o numérico
            $label = is_array($item) ? ($item['label'] ?? $item) : $item;
            $isSelected = ($index === $this->selectedSubMenuItem);
            
            if ($isSelected) {
                // [ Label ]
                $menuText .= "\033[1;36m[\033[1;37m " . $label . " \033[1;36m]\033[0m ";
                $menuVisualLength += 1 + 1 + mb_strwidth($label) + 1 + 1 + 1; // [ + space + label + space + ] + space 
            } else {
                //  Label 
                $menuText .= "\033[36m " . $label . " \033[0m ";
                $menuVisualLength += 1 + mb_strwidth($label) + 1 + 1; // space + label + space + space
            }
        }
        
        $availableSpace = $this->width - 4;
        $padding = $availableSpace - $menuVisualLength;
        
        echo $menuText;
        echo str_repeat(" ", max(0, $padding));
        echo " \033[36m║\033[0m\n";
    }

    /**
     * Renderiza el área de trabajo
     */
    private function renderWorkArea(callable $contentRenderer, int $height): void
    {
        // Renderizar contenido
        ob_start();
        call_user_func($contentRenderer, $this->width - 2, $height);
        $content = ob_get_clean();
        
        // Dividir en líneas
        $lines = explode("\n", $content);
        
        // Renderizar cada línea con bordes, asegurando el ancho exacto
        for ($i = 0; $i < $height; $i++) {
            echo "\033[36m║\033[0m";
            
            if (isset($lines[$i])) {
                $line = $lines[$i];
                $lineLength = $this->stripAnsiLength($line);
                $maxWidth = $this->width - 2;
                
                if ($lineLength > $maxWidth) {
                    // Línea muy larga: truncar
                    $truncated = $this->truncateWithAnsi($line, $maxWidth);
                    echo $truncated;
                } elseif ($lineLength < $maxWidth) {
                    // Línea corta: rellenar con espacios
                    echo $line;
                    echo str_repeat(" ", $maxWidth - $lineLength);
                } else {
                    // Línea exacta
                    echo $line;
                }
            } else {
                // Línea vacía: rellenar completamente
                echo str_repeat(" ", $this->width - 2);
            }
            
            echo "\033[36m║\033[0m\n";
        }
    }
    
    /**
     * Trunca una cadena con códigos ANSI a un ancho específico
     */
    private function truncateWithAnsi(string $text, int $maxWidth): string
    {
        $result = '';
        $visibleLength = 0;
        $inAnsi = false;
        $ansiBuffer = '';
        
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            
            // Detectar inicio de secuencia ANSI
            if ($char === "\033" || $char === "\x1b") {
                $inAnsi = true;
                $ansiBuffer = $char;
                continue;
            }
            
            if ($inAnsi) {
                $ansiBuffer .= $char;
                // Fin de secuencia ANSI (letra)
                if (ctype_alpha($char)) {
                    $result .= $ansiBuffer;
                    $inAnsi = false;
                    $ansiBuffer = '';
                }
                continue;
            }
            
            // Carácter visible
            if ($visibleLength < $maxWidth) {
                $result .= $char;
                $visibleLength++;
            } else {
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Renderiza la barra de estado
     */
    private function renderStatusBar(): void
    {
        echo "\033[36m╠" . str_repeat("═", $this->width - 2) . "╣\033[0m\n";
        echo "\033[36m║\033[0m ";
        
        $statusText = "\033[32mF1\033[0m=Ayuda  " .
                     "\033[32mF12\033[0m=Salir  " .
                     "\033[37m←→\033[0m=Menú  " .
                     "\033[37m↑↓\033[0m=Navegar";
        
        $statusVisualLength = mb_strwidth("F1=Ayuda  F12=Salir  ←→=Menú  ↑↓=Navegar");
        $padding = $this->width - 4 - $statusVisualLength;
        
        echo $statusText;
        echo str_repeat(" ", max(0, $padding));
        echo " \033[36m║\033[0m\n";
    }

    /**
     * Renderiza el borde inferior
     */
    private function renderBottomBorder(): void
    {
        // Sin salto de línea al final para evitar scroll en la última línea de la terminal
        echo "\033[36m╚" . str_repeat("═", $this->width - 2) . "╝\033[0m";
    }

    /**
     * Calcula longitud sin códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        // NO USADO para layout visual, usar mb_strwidth sobre el texto limpio
        $clean = preg_replace('/\033\[[0-9;]*m/', '', $text);
        return mb_strwidth($clean);
    }
    
    /**
     * Obtiene el ancho disponible para el contenido
     */
    public function getContentWidth(): int
    {
        return $this->width - 2;
    }
    
    /**
     * Obtiene la altura disponible para el contenido
     */
    public function getContentHeight(): int
    {
        // Cálculo de líneas fijas ocupadas:
        // TopBorder(1) + Header(1) + HeaderSep(1) = 3
        // MenuWithSep(2 o 3)
        // StatusSep(1) + Status(1) + BottomBorder(1) = 3
        
        $menuHeight = !empty($this->subMenuItems) ? self::MENU_HEIGHT_DOUBLE : self::MENU_HEIGHT_SINGLE;
        
        // Total deducible = 6 (fijos arriba/abajo) + altura menú
        return $this->height - 6 - $menuHeight;
    }
}
