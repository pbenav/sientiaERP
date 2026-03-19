<?php

namespace App\NexErpTui\Display;

use App\NexErpTui\Input\KeyHandler;
use App\NexErpTui\Input\FunctionKeyMapper;

/**
 * MainMenu - Menú principal con FullScreenLayout
 * 
 * Menú horizontal navegable con submenús desplegables
 */
class MainMenu
{
    private KeyHandler $keyHandler;
    private Screen $screen;
    private FullScreenLayout $layout;
    private ColorTheme $theme;
    
    private array $menuStructure;
    private int $selectedMenuIndex = 0;
    private ?int $selectedSubIndex = null;
    private ?string $currentMenu = null;
    
    public function __construct(
        KeyHandler $keyHandler,
        Screen $screen,
        array $menuStructure,
        string $themeName = ColorTheme::IBM_GREEN
    ) {
        $this->keyHandler = $keyHandler;
        $this->screen = $screen;
        $this->menuStructure = $menuStructure;
        $this->theme = new ColorTheme($themeName);
        $this->layout = new FullScreenLayout($screen);
    }
    
    /**
     * Ejecuta el menú principal
     */
    public function run(string $userName = 'Usuario'): ?array
    {
        file_put_contents('/tmp/tui_debug.log', "MainMenu::run iniciado\n", FILE_APPEND);
        $this->keyHandler->setRawMode(true);
        
        // Habilitar señales asíncronas para redimensionado
        pcntl_async_signals(true);
        
        // Manejador de señal de cambio de tamaño (SIGWINCH)
        pcntl_signal(SIGWINCH, function($signo) use ($userName) {
            // Layout se auto-actualiza en su método render(), 
            // pero necesitamos forzar un repintado inmediato si estamos esperando input.
            // Para simplificar, la próxima iteración del bucle actualizará el layout.
            // En sistemas complejos, podríamos inyectar un evento de "RESIZE" en la cola de input.
            
            // Forzar actualización de dimensiones layout
            // (Aunque render() lo hace, es bueno tenerlo explícito si cambiamos la lógica)
        });
        
        while (true) {
            // Preparar items del menú principal
            $menuItems = array_map(fn($m) => $m['label'], $this->menuStructure);
            
            // Preparar items del submenú actual
            $currentSubMenuItems = $this->menuStructure[$this->selectedMenuIndex]['items'];
            $subMenuLabels = array_map(fn($i) => $i['label'], $currentSubMenuItems);
            
            // Configurar layout
            $this->layout
                ->setCompanyName('nexERP')
                ->setTitle('MENÚ PRINCIPAL') // Título fijo o dinámico
                ->setMenuItems($menuItems)
                ->setSelectedMenuItem($this->selectedMenuIndex)
                ->setSubMenuItems($subMenuLabels)
                ->setSelectedSubMenuItem($this->selectedSubIndex ?? -1); // -1 si no está activo
            
            // Renderizar
            $this->layout->render(function($width, $height) use ($userName) {
                $this->renderInfoArea($width, $height, $userName);
            });
            
            // Procesar entrada
            $rawKey = $this->keyHandler->waitForKey();
            file_put_contents('/tmp/tui_debug.log', "Key received hex: " . bin2hex($rawKey) . "\n", FILE_APPEND);
            
            $key = FunctionKeyMapper::mapKey($rawKey);
            file_put_contents('/tmp/tui_debug.log', "Mapped key: $key\n", FILE_APPEND);
            
            $action = $this->handleInput($key);
            
            if ($action) {
                file_put_contents('/tmp/tui_debug.log', "Action triggered: " . print_r($action, true) . "\n", FILE_APPEND);
                
                // Restaurar configuración original al salir de una acción
                $this->keyHandler->setRawMode(true);
                // Si la acción es 'exit', retornarla para salir del bucle
                if (($action['action'] ?? '') === 'exit') {
                    return $action;
                }
                // Si no es exit, significa que ya se ejecutó la acción (ej. un CRUD)
                // y volvemos al menú. No necesitamos retornar nada, solo seguir el bucle.
                // PERO: La arquitectura actual espera retornar la acción para que el caller la ejecute.
                return $action;
            }
        }
    }
    
    /**
     * Renderiza el área de información (dashboard simple)
     */
    private function renderInfoArea(int $width, int $height, string $userName): void
    {
        $info = $this->theme->get('info');
        $text = $this->theme->get('text');
        $highlight = $this->theme->get('highlight');
        $reset = $this->theme->get('reset');
        $selected = $this->theme->get('selected'); // Verde brillante

        echo "\n";
        echo "  {$info}Usuario:{$reset} {$text}{$userName}{$reset}\n";
        echo "\n";
        
        if ($this->selectedSubIndex === null) {
            echo "  {$highlight}Navegue con ←→ por el menú principal.{$reset}\n";
            echo "  {$highlight}Pulse ↓ para entrar al submenú.{$reset}\n";
        } else {
            echo "  {$selected}Navegue con ←→ por el submenú.{$reset}\n";
            echo "  {$selected}Pulse ENTER para acceder a la opción.{$reset}\n";
            echo "  {$text}Pulse ↑ para volver al menú principal.{$reset}\n";
        }
        
        // Aquí se podría mostrar un dashboard real
    }
    
    /**
     * Maneja la entrada del usuario
     */
    private function handleInput(string $key): ?array
    {
        // Navegación Horizontal
        if ($key === 'LEFT') {
            if ($this->selectedSubIndex !== null) {
                // Navegar submenú
                $limit = count($this->menuStructure[$this->selectedMenuIndex]['items']);
                if ($this->selectedSubIndex > 0) {
                    $this->selectedSubIndex--;
                } 
            } else {
                // Navegar menú principal
                if ($this->selectedMenuIndex > 0) {
                    $this->selectedMenuIndex--;
                    // Resetear submenú al cambiar de menú padre
                    //$this->selectedSubIndex = null; 
                }
            }
            return null;
        }
        
        if ($key === 'RIGHT') {
            if ($this->selectedSubIndex !== null) {
                // Navegar submenú
                $limit = count($this->menuStructure[$this->selectedMenuIndex]['items']);
                if ($this->selectedSubIndex < $limit - 1) {
                    $this->selectedSubIndex++;
                }
            } else {
                // Navegar menú principal
                if ($this->selectedMenuIndex < count($this->menuStructure) - 1) {
                    $this->selectedMenuIndex++;
                    // Resetear submenú al cambiar de menú padre
                    //$this->selectedSubIndex = null;
                }
            }
            return null;
        }
        
        // Navegación Vertical
        if ($key === 'DOWN' && $this->selectedSubIndex === null) {
            // Bajar al submenú
            $currentItems = $this->menuStructure[$this->selectedMenuIndex]['items'];
            if (!empty($currentItems)) {
                $this->selectedSubIndex = 0;
            }
            return null;
        }
        
        if ($key === 'UP' && $this->selectedSubIndex !== null) {
            // Subir al menú principal
            $this->selectedSubIndex = null;
            return null;
        }
        
        // Enter para ejecutar acción
        if ($key === 'ENTER' && $this->selectedSubIndex !== null) {
            $menu = $this->menuStructure[$this->selectedMenuIndex];
            $item = $menu['items'][$this->selectedSubIndex];
            
            return [
                'action' => $item['action'],
                'menu' => $menu['label'],
                'item' => $item['label']
            ];
        }
        
        // Salir
        if ($key === 'F12' || $key === 'ESC') {
            return ['action' => 'exit'];
        }
        
        return null;
    }
}
