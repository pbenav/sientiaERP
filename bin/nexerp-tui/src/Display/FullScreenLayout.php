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
    private const HEADER_HEIGHT = 3;
    private const MENU_HEIGHT_SINGLE = 2; // Solo menú principal
    private const MENU_HEIGHT_DOUBLE = 4; // Menú + Submenú
    private const STATUS_HEIGHT = 1;
    
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
        // Línea 1: Empresa y Fecha
        echo "\033[36m║\033[0m ";
        echo "\033[1;36m" . $this->companyName . "\033[0m"; // Cyan brillante
        
        $date = date('d/m/Y H:i');
        $headerLeftLen = mb_strlen($this->companyName);
        $dateLen = mb_strlen($date);
        
        $padding = $this->width - 4 - $headerLeftLen - $dateLen;
        
        echo str_repeat(" ", max(0, $padding));
        echo "\033[1;37m" . $date . "\033[0m"; // Blanco brillante
        echo " \033[36m║\033[0m\n";
        
        // Línea 2: Título Centrado (o espacio)
        if (!empty($this->currentTitle)) {
             echo "\033[36m║\033[0m " . str_repeat(" ", $this->width - 4) . " \033[36m║\033[0m\n";
             
             $titleLen = mb_strlen($this->currentTitle);
             $paddingTotal = $this->width - 4 - $titleLen;
             $padLeft = (int)floor($paddingTotal / 2);
             $padRight = (int)ceil($paddingTotal / 2);
             
             echo "\033[36m║\033[0m ";
             echo str_repeat(" ", max(0, $padLeft));
             echo "\033[1;33m" . mb_strtoupper($this->currentTitle) . "\033[0m"; // Amarillo brillante
             echo str_repeat(" ", max(0, $padRight));
             echo " \033[36m║\033[0m\n";
        } else {
             echo "\033[36m║\033[0m " . str_repeat(" ", $this->width - 4) . " \033[36m║\033[0m\n";
             echo "\033[36m║\033[0m " . str_repeat(" ", $this->width - 4) . " \033[36m║\033[0m\n";
        }
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

    
    // ...

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
        $this->screen->clear();
        
        // Borde superior completo
        $this->renderTopBorder();
        
        // Cabecera
        $this->renderHeader();
        
        // Separador después de cabecera
        $this->renderSeparator();
        
        // Menú horizontal
        $this->renderMenu();
        
        // Submenú horizontal (si existe)
        $hasSubMenu = !empty($this->subMenuItems);
        if ($hasSubMenu) {
            $this->renderSeparator(); // Separador entre menú y submenú
            $this->renderSubMenu();
        }
        
        // Separador después de menú (o submenú)
        $this->renderSeparator();
        
        // Calcular altura real del menú
        $menuHeight = $hasSubMenu ? self::MENU_HEIGHT_DOUBLE : self::MENU_HEIGHT_SINGLE;
        
        // Área de trabajo (contenido)
        // Altura total - Header(3) - Menú(2 o 4) - Status(1) - Bordes(2 sup/inf + 1 linea status) = Total - X
        // Bordes fijos verticales: Top(1), Sep1(1), Sep2(1), Sep3(1) [si submenu +1], SepStatus(1), Bottom(1)
        // Simplificado: Total - (HeaderContent + MenuContent + StatusContent + Borders)
        
        $bordersCount = 4; // Top, HeaderSep, MenuSep, StatusSep
        if ($hasSubMenu) $bordersCount++; // SubMenuSep
        
        $usedHeight = self::HEADER_HEIGHT + 1 + ($hasSubMenu ? 2 : 1) + 1 + 1; 
        // Header(3-1 bordes) es confuso. Mejor usar la lógica visual:
        // Top Border: 1
        // Header: 1
        // Header Sep: 1
        // Menu: 1
        // (SubMenu Sep: 1)
        // (SubMenu: 1)
        // Menu Sep: 1
        // Work Area: N
        // Status Sep: 1
        // Status: 1
        // Bottom Border: 1
        
        $fixedLines = 1 + 1 + 1 + 1 + 1 + 1 + 1 + 1; // 8 líneas fijas sin submenú
        if ($hasSubMenu) $fixedLines += 2; // +1 Sep, +1 SubMenu Content
        
        $workAreaHeight = $this->height - $fixedLines;
        if ($workAreaHeight < 5) $workAreaHeight = 5; // Mínimo de seguridad

        $this->renderWorkArea($contentRenderer, $workAreaHeight);
        
        // Barra de estado
        $this->renderStatusBar();
        
        // Borde inferior
        $this->renderBottomBorder();
    }
    
    // ...

    /**
     * Renderiza el menú horizontal
     */
    private function renderMenu(): void
    {
        echo "\033[36m║\033[0m ";
        
        $menuText = '';
        $menuLength = 0;
        
        foreach ($this->menuItems as $index => $item) {
            $isSelected = ($index === $this->selectedMenuItem);
            
            if ($isSelected) {
                // Destacado
                $menuText .= "\033[1;33m► " . mb_strtoupper($item) . " \033[0m  ";
                $menuLength += mb_strlen($item) + 4; // ► + espacio + item + espacio + espacio
            } else {
                $menuText .= "\033[37m  " . $item . " \033[0m  ";
                $menuLength += mb_strlen($item) + 4; // espacios
            }
        }
        
        // Rellenar
        $availableSpace = $this->width - 4;
        $padding = $availableSpace - $menuLength;
        
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
        $menuLength = 0;
        
        foreach ($this->subMenuItems as $index => $item) {
            // Manejamos claves de array si es asociativo o numérico
            $label = is_array($item) ? ($item['label'] ?? $item) : $item;
            $isSelected = ($index === $this->selectedSubMenuItem);
            
            if ($isSelected) {
                $menuText .= "\033[1;36m[\033[1;37m " . $label . " \033[1;36m]\033[0m ";
                $menuLength += mb_strlen($label) + 5; 
            } else {
                $menuText .= "\033[36m " . $label . " \033[0m ";
                $menuLength += mb_strlen($label) + 3;
            }
        }
        
        $availableSpace = $this->width - 4;
        $padding = $availableSpace - $menuLength;
        
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
        
        $statusLength = $this->stripAnsiLength($statusText);
        $padding = $this->width - 4 - $statusLength;
        
        echo $statusText;
        echo str_repeat(" ", max(0, $padding));
        echo " \033[36m║\033[0m\n";
    }
    
    /**
     * Renderiza el borde inferior
     */
    private function renderBottomBorder(): void
    {
        echo "\033[36m╚" . str_repeat("═", $this->width - 2) . "╝\033[0m\n";
    }
    
    /**
     * Calcula longitud sin códigos ANSI
     */
    private function stripAnsiLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
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
        return $this->height - self::HEADER_HEIGHT - self::MENU_HEIGHT_SINGLE - self::STATUS_HEIGHT - 3;
    }
}
