<?php

namespace App\SienteErpTui\Display;

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
    private string $companyName = 'sienteERP';
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
        $color = $this->screen->color('border');
        $reset = $this->screen->reset();
        echo "{$color}╔" . str_repeat("═", $this->width - 2) . "╗{$reset}\n";
    }

    /**
     * Renderiza la cabecera (Empresa, Título, Fecha)
     */
    private function renderHeader(): void
    {
        $date = date('d/m/Y H:i');
        
        $compLen = mb_strwidth($this->companyName);
        $title = mb_strtoupper($this->currentTitle);
        $titleLen = mb_strwidth($title);
        $dateLen = mb_strwidth($date);
        
        $innerSpace = $this->width - 4;
        $startTitle = (int) floor(($innerSpace - $titleLen) / 2);
        
        $spaces1 = $startTitle - $compLen;
        if ($spaces1 < 1) $spaces1 = 1;
        
        $currentPos = $compLen + $spaces1 + $titleLen;
        $datePos = $innerSpace - $dateLen;
        $spaces2 = $datePos - $currentPos;
        if ($spaces2 < 1) $spaces2 = 1;

        $totalLen = $compLen + $spaces1 + $titleLen + $spaces2 + $dateLen;
        if ($totalLen > $innerSpace) {
            $excess = $totalLen - $innerSpace;
            if ($spaces2 > $excess + 1) {
                $spaces2 -= $excess;
            } elseif ($spaces1 > $excess + 1) {
                $spaces1 -= $excess;
            }
        }

        // Colores del tema
        $borderCol = $this->screen->color('border');
        $headerBg = $this->screen->color('header_bg');
        $headerFg = $this->screen->color('header_fg');
        $reset = $this->screen->reset();

        // Renderizar línea con fondo
        echo "{$borderCol}║{$reset} ";
        echo "{$headerBg}{$headerFg} "; // Espacio inicial fondo
        echo $this->companyName;
        echo str_repeat(" ", (int)$spaces1);
        echo $title;
        echo str_repeat(" ", (int)$spaces2);
        echo $date;
        echo " {$reset} "; // Espacio final fondo + reset
        echo "{$borderCol}║{$reset}\n";
    }
    
    /**
     * Renderiza un separador horizontal
     */
    private function renderSeparator(): void
    {
        $color = $this->screen->color('border');
        $reset = $this->screen->reset();
        echo "{$color}╠" . str_repeat("═", $this->width - 2) . "╣{$reset}\n";
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
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset();
        $menuSelectedCol = $this->screen->color('menu_selected');
        $menuNormalCol = $this->screen->color('menu_normal');

        echo "{$borderCol}║{$reset} ";
        
        $menuText = '';
        $menuVisualLength = 0;
        
        foreach ($this->menuItems as $index => $item) {
            $isSelected = ($index === $this->selectedMenuItem);
            $label = mb_strtoupper($item);
            
            if ($isSelected) {
                $menuText .= "{$menuSelectedCol} {$label} {$reset}  ";
                $menuVisualLength += mb_strwidth($label) + 4; 
            } else {
                $menuText .= "{$menuNormalCol}  " . $item . " {$reset}  ";
                $menuVisualLength += 2 + mb_strwidth($item) + 3;
            }
        }
        
        $availableSpace = $this->width - 4;
        $padding = $availableSpace - $menuVisualLength;
        
        echo $menuText;
        echo str_repeat(" ", max(0, (int)$padding));
        echo " {$borderCol}║{$reset}\n";
    }

    /**
     * Renderiza el submenú horizontal
     */
    private function renderSubMenu(): void
    {
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset();
        $selectedCol = $this->screen->color('selected');
        $infoCol = $this->screen->color('info');

        echo "{$borderCol}║{$reset}   "; 
        
        $menuText = '';
        $menuVisualLength = 1; // Por el espacio extra inicial
        
        foreach ($this->subMenuItems as $index => $item) {
            $label = is_array($item) ? ($item['label'] ?? $item) : $item;
            $isSelected = ($index === $this->selectedSubMenuItem);
            
            if ($isSelected) {
                $menuText .= "{$selectedCol} " . $label . " {$reset} ";
                $menuVisualLength += mb_strwidth($label) + 3; 
            } else {
                $menuText .= "{$infoCol} " . $label . " {$reset} ";
                $menuVisualLength += mb_strwidth($label) + 3;
            }
        }
        
        $availableSpace = $this->width - 4 - 2;
        $padding = $availableSpace - $menuVisualLength;
        
        echo $menuText;
        echo str_repeat(" ", max(0, (int)$padding));
        echo " {$borderCol}║{$reset}\n";
    }

    /**
     * Renderiza el área de trabajo
     */
    private function renderWorkArea(callable $contentRenderer, int $height): void
    {
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset();
        
        ob_start();
        call_user_func($contentRenderer, $this->width - 2, $height);
        $content = ob_get_clean();
        
        $lines = explode("\n", $content);
        
        for ($i = 0; $i < $height; $i++) {
            echo "{$borderCol}║{$reset}";
            
            if (isset($lines[$i])) {
                $line = $lines[$i];
                $lineLength = $this->stripAnsiLength($line);
                $maxWidth = $this->width - 2;
                
                if ($lineLength > $maxWidth) {
                    $truncated = $this->truncateWithAnsi($line, $maxWidth);
                    echo $truncated;
                } elseif ($lineLength < $maxWidth) {
                    echo $line;
                    echo str_repeat(" ", $maxWidth - $lineLength);
                } else {
                    echo $line;
                }
            } else {
                echo str_repeat(" ", $this->width - 2);
            }
            
            echo "{$borderCol}║{$reset}\n";
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
        $borderCol = $this->screen->color('border');
        $reset = $this->screen->reset();
        $fKeyCol = $this->screen->color('function_key');
        $textCol = $this->screen->color('text');

        echo "{$borderCol}╠" . str_repeat("═", $this->width - 2) . "╣{$reset}\n";
        echo "{$borderCol}║{$reset} ";
        
        $statusText = "{$fKeyCol}F1{$reset}={$textCol}Ayuda  " .
                     "{$fKeyCol}F12{$reset}={$textCol}Salir  " .
                     "{$fKeyCol}←→{$reset}={$textCol}Menú   " .
                     "{$fKeyCol}↑↓{$reset}={$textCol}Navegar";
        
        $statusVisualLength = mb_strwidth("F1=Ayuda  F12=Salir  ←→=Menú   ↑↓=Navegar");
        $padding = $this->width - 4 - $statusVisualLength;
        
        echo $statusText;
        echo str_repeat(" ", max(0, (int)$padding));
        echo " {$borderCol}║{$reset}\n";
    }

    /**
     * Renderiza el borde inferior
     */
    private function renderBottomBorder(): void
    {
        $color = $this->screen->color('border');
        $reset = $this->screen->reset();
        echo "{$color}╚" . str_repeat("═", $this->width - 2) . "╝{$reset}";
    }

    private function stripAnsiLength(string $text): int
    {
        $clean = preg_replace('/\033\[[0-9;]*m/', '', $text);
        // También limpiar códigos 38;5;Xm y 48;5;Xm (256 colores)
        $clean = preg_replace('/\033\[[0-9;:]*[mK]/', '', $clean);
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
