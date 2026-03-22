<?php

namespace App\SienteErpTui\Actions;

use App\SienteErpTui\ErpClient;
use App\SienteErpTui\Display\Screen;
use App\SienteErpTui\Display\ListController;
use App\SienteErpTui\Display\FormController;
use App\SienteErpTui\Input\KeyHandler;

class DocumentosActions
{
    private ErpClient $client;
    private Screen $screen;
    private KeyHandler $keyHandler;
    private DetailsActions $details;

    public function __construct(ErpClient $client, Screen $screen, KeyHandler $keyHandler, DetailsActions $details)
    {
        $this->client = $client;
        $this->screen = $screen;
        $this->keyHandler = $keyHandler;
        $this->details = $details;
    }

    /**
     * Listar documentos por tipo
     */
    public function listar(string $tipo): void
    {
        $tipoLabels = [
            'presupuesto' => 'Presupuestos',
            'pedido' => 'Pedidos Venta',
            'albaran' => 'Albaranes Venta',
            'factura' => 'Facturas Venta',
            'recibo' => 'Recibos Venta',
            'pedido_compra' => 'Pedidos Compra',
            'albaran_compra' => 'Albaranes Compra',
            'factura_compra' => 'Facturas Compra',
            'recibo_compra' => 'Recibos Compra',
        ];

        $listController = new ListController($this->keyHandler, $this->screen, $tipoLabels[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo)));
        
        // Definir columnas
        // Definir columnas con anchos fijos y truncamiento automático
        // Definir columnas con anchos optimizados
        // Definir columnas responsivas
        $listController->setColumns([
            'numero' => [
                'label' => 'Número', 
                'width' => 15
            ],
            'fecha' => [
                'label' => 'Fecha', 
                'width' => 12,
                'formatter' => function($row) {
                    return substr($row['fecha'] ?? '', 0, 10);
                }
            ],
            'cliente' => [
                'label' => 'Cliente', 
                'width' => 20, // Ancho mínimo
                'flex' => true, // Esta columna se expandirá
                'formatter' => function($row) {
                    return $row['tercero']['nombre_comercial'] ?? '---';
                }
            ],
            'total' => [
                'label' => 'Total', 
                'width' => 15, // Un poco más de aire 
                'align' => 'right', 
                'formatter' => function($row) {
                    return number_format($row['total'] ?? 0, 2, ',', '.') . ' €';
                }
            ],
            'estado' => [
                'label' => 'Estado', 
                'width' => 12, 
                'formatter' => function($row) {
                    return ucfirst($row['estado'] ?? 'borrador');
                }
            ]
        ]);
        
        // Callback para obtener datos
        $listController->onFetch(function($page, $perPage) use ($tipo) {
            return $this->client->getDocumentos($tipo, $page, $perPage);
        });
        
        // Callback para crear
        $listController->onCreate(function() use ($tipo) {
            $this->crear($tipo);
        });
        
        // Callback para editar
        $listController->onEdit(function($documento) {
            $this->editar($documento['id']);
        });
        
        // Callback para ver detalles
        $listController->onView(function($documento) {
            $this->details->renderDocumento($documento['id']);
        });
        
        // Ejecutar
        $listController->run();
    }

    public function editar(int $id): void
    {
        try {
            $doc = $this->client->getDocumento($id);
            
            // Formatear la fecha correctamente
            $fechaFormateada = $doc['fecha'] ?? date('Y-m-d');
            if (strtotime($fechaFormateada)) {
                $fechaFormateada = date('Y-m-d', strtotime($fechaFormateada));
            }
            
            $this->keyHandler->setRawMode(true);
            
            // Estado del editor
            $editandoCabecera = true; // true = cabecera, false = líneas
            $fecha = $fechaFormateada;
            $lineas = $doc['lineas'] ?? [];
            $selectedLineIndex = 0;
            $isEditing = false;
            
            while (true) {
                // Renderizar pantalla completa
                $this->renderDocumentoEditor($doc, $fecha, $lineas, $editandoCabecera, $selectedLineIndex, $isEditing);
                
                $rawKey = $this->keyHandler->waitForKey();
                $key = \App\SienteErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
                
                // MODO EDICIÓN (Cabecera - Fecha)
                if ($isEditing && $editandoCabecera) {
                    if ($key === 'ENTER') {
                        $isEditing = false;
                    } elseif ($key === 'BACKSPACE' || $key === 'DELETE') {
                        if (mb_strlen($fecha) > 0) {
                            $fecha = mb_substr($fecha, 0, -1);
                        }
                    } elseif (strlen($key) === 1 && (is_numeric($key) || $key === '-')) {
                        if (mb_strlen($fecha) < 10) {
                            $fecha .= $key;
                        }
                    }
                    continue;
                }

                // Cambiar entre cabecera y líneas
                if ($key === 'TAB') {
                    $editandoCabecera = !$editandoCabecera;
                    $isEditing = false; 
                    if (!$editandoCabecera && empty($lineas)) {
                        $selectedLineIndex = -1;
                    }
                }
                // Cambiar a modo edición
                elseif ($key === 'ENTER' && $editandoCabecera) {
                    $isEditing = true;
                }
                // Guardar
                elseif ($key === 'F10') {
                    try {
                        // Reindexar array de líneas (importante después de eliminar)
                        $lineasReindexadas = array_values($lineas);
                        
                        $data = [
                            'fecha' => $fecha,
                            'tercero_id' => $doc['tercero_id'],
                            'lineas' => $lineasReindexadas
                        ];
                        
                        $this->client->updateDocumento($id, $data);
                        $this->showMessage('Documento actualizado correctamente', 'success');
                        return;
                    } catch (\Exception $e) {
                        $this->showMessage('Error al actualizar: ' . $e->getMessage(), 'error');
                        return;
                    }
                }
                // Cancelar
                elseif ($key === 'F12' || $key === 'ESC') {
                    return;
                }
                // Editar líneas
                else {
                    if ($key === 'UP' && $selectedLineIndex > 0) {
                        $selectedLineIndex--;
                    } elseif ($key === 'DOWN' && $selectedLineIndex < count($lineas) - 1) {
                        $selectedLineIndex++;
                    }
                    // Añadir línea
                    elseif ($key === 'F5') {
                        $lineModal = new \App\SienteErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
                        $nuevaLinea = $lineModal->run(function($searchText) {
                            return $this->client->searchProducto($searchText);
                        });
                        if ($nuevaLinea) {
                            $lineas[] = $nuevaLinea;
                            $selectedLineIndex = count($lineas) - 1;
                        }
                    }
                    // Editar línea - Abrir modal completo para cambiar producto
                    elseif (($key === 'F2' || $key === 'F6') && !empty($lineas) && $selectedLineIndex >= 0) {
                        $lineaActual = $lineas[$selectedLineIndex];
                        
                        // Usar el modal completo para editar (permite cambiar producto)
                        $lineModal = new \App\SienteErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
                        
                        // Pre-cargar los valores actuales en el modal
                        // (El modal se resetea al inicio, así que necesitamos una versión que acepte valores iniciales)
                        // Por ahora, simplemente abrimos el modal y el usuario puede buscar el producto de nuevo
                        
                        $lineaEditada = $lineModal->run(function($searchText) {
                            return $this->client->searchProducto($searchText);
                        });
                        
                        if ($lineaEditada) {
                            // Reemplazar la línea completa con la nueva
                            $lineas[$selectedLineIndex] = $lineaEditada;
                        }
                    }
                    // Eliminar línea
                    elseif ($key === 'F8' && !empty($lineas) && $selectedLineIndex >= 0) {
                        array_splice($lineas, $selectedLineIndex, 1);
                        if ($selectedLineIndex >= count($lineas)) {
                            $selectedLineIndex = max(0, count($lineas) - 1);
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->showMessage('Error al cargar documento: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Renderiza el editor de documento completo
     */
    private function renderDocumentoEditor(array $doc, string $fecha, array $lineas, bool $editandoCabecera, int $selectedLineIndex, bool $isEditing = false): void
    {
        $this->screen->clear();
        $width = 80;
        $innerW = $width - 2;
        
        $borderCol = $this->screen->color('border');
        $titleCol = $this->screen->color('title');
        $textCol = $this->screen->color('text');
        $highlightCol = $this->screen->color('highlight');
        $reset = $this->screen->reset('form_bg');
        
        // --- 1. HEADER ---
        echo "{$borderCol}╔" . str_repeat("═", $innerW) . "╗\n";
        
        $title = "EDITAR DOCUMENTO";
        $titleLen = mb_strlen($title);
        $padLeft = (int)floor(($innerW - $titleLen) / 2);
        $padRight = $innerW - $titleLen - $padLeft;
        
        echo "║" . str_repeat(" ", $padLeft) . "{$titleCol}" . $title . "{$borderCol}" . str_repeat(" ", $padRight) . "║\n";
        echo "╠" . str_repeat("═", $innerW) . "╣{$reset}\n";
        
        // --- 2. BODY (Buffered) ---
        $linesArr = [];
        
        // CABECERA SECCIÓN
        $cabeceraColor = $editandoCabecera ? $highlightCol : $textCol;
        $linesArr[] = "  {$cabeceraColor}CABECERA:{$reset}";
        $linesArr[] = "  {$borderCol}" . str_repeat("─", $innerW - 4) . "{$reset}";
        
        $linesArr[] = "  {$textCol}Número:{$reset} {$doc['numero']}";
        
        $fechaStr = $fecha;
        if ($editandoCabecera && $isEditing) {
            $fechaStr = "{$this->screen->color('selected')} {$fecha} _ {$reset}";
        } elseif ($editandoCabecera) {
            $fechaStr = "{$highlightCol}{$fecha}{$reset}";
        }
        
        $linesArr[] = "  {$textCol}Fecha:{$reset} {$fechaStr}";
        $linesArr[] = "  {$textCol}Cliente:{$reset} " . ($doc['tercero']['nombre_comercial'] ?? '');

        $linesArr[] = "  {$textCol}Estado:{$reset} " . ucfirst($doc['estado'] ?? 'borrador');
        $linesArr[] = ""; 
        
        // LÍNEAS SECCIÓN
        $lineasColor = !$editandoCabecera ? $highlightCol : $textCol;
        $linesArr[] = "  {$lineasColor}LÍNEAS:{$reset}";
        $linesArr[] = "  {$borderCol}" . str_repeat("─", $innerW - 4) . "{$reset}";
        
        if (empty($lineas)) {
            $linesArr[] = "  {$highlightCol}No hay líneas. Presione F5 para añadir.{$reset}";
            $linesArr[] = "";
        } else {
            // Cabecera tabla
            $headerStr = "  {$borderCol}" .
                str_pad("Producto", 38) .
                str_pad("Cant.", 12, " ", STR_PAD_LEFT) .
                str_pad("Precio", 14, " ", STR_PAD_LEFT) .
                str_pad("Total", 12, " ", STR_PAD_LEFT) .
                "{$reset}";
            $linesArr[] = $headerStr;
             
            $totalGeneral = 0;
            foreach ($lineas as $index => $linea) {
                $isSelected = (!$editandoCabecera && $index === $selectedLineIndex);
                $prefix = $isSelected ? "{$highlightCol}► " : "  ";
                $color = $isSelected ? $highlightCol : $textCol;
                
                $cantidad = $linea['cantidad'] ?? 0;
                $precio = $linea['precio_unitario'] ?? 0;
                $descuento = $linea['descuento'] ?? 0;
                $total = $cantidad * $precio * (1 - $descuento / 100);
                $totalGeneral += $total;
                
                $cantidadStr = number_format($cantidad, 2, ',', '.');
                $precioStr = str_pad(number_format($precio, 2, ',', '.') . ' €', 14, " ", STR_PAD_LEFT);
                $totalStr = str_pad(number_format($total, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
                
                $lineStr = $prefix . $color .
                    str_pad(mb_substr($linea['descripcion'] ?? '', 0, 35), 38) .
                    str_pad($cantidadStr, 12, " ", STR_PAD_LEFT) .
                    $precioStr .
                    $totalStr .
                    "{$reset}";
                $linesArr[] = $lineStr;
            }
            
            $linesArr[] = "  {$borderCol}" . str_repeat("─", $innerW - 4) . "{$reset}";
            
            $totalGeneralNum = number_format($totalGeneral, 2, ',', '.');
            $totalGeneralStr = str_pad($totalGeneralNum . ' €', 12, " ", STR_PAD_LEFT);
            $label = "TOTAL:";
            $paddingLen = $innerW - 4 - mb_strlen($label) - 12;
            
            $linesArr[] = "  {$this->screen->color('success')}" . $label . str_repeat(" ", max(0, $paddingLen)) . $totalGeneralStr . "{$reset}";
        }
        
        while (count($linesArr) < 15) {
            $linesArr[] = "";
        }

        foreach ($linesArr as $l) {
             $visibleLen = $this->screen->strWidth($l);
             $padding = max(0, $innerW - $visibleLen);
             echo "{$borderCol}║{$reset}" . $l . str_repeat(" ", (int)$padding) . "{$borderCol}║{$reset}\n";
        }
        
        // --- 3. FOOTER ---
        echo "{$borderCol}╠" . str_repeat("═", $innerW) . "╣{$reset}\n";
        
        $fKey = $this->screen->color('function_key');
        $modeStr = $isEditing ? "{$this->screen->color('selected')}MODO EDICIÓN{$reset}" : "{$this->screen->color('info')}MODO NAVEGACIÓN{$reset}";
        
        $helpText = implode("  ", $helpParts);
        $helpLen = $this->screen->strWidth($helpText);
        $helpPad = max(0, $innerW - 1 - $helpLen);
        
        echo "{$borderCol}║ {$reset}" . $helpText . str_repeat(" ", (int)$helpPad) . "{$borderCol}║{$reset}\n";
        echo "{$borderCol}╚" . str_repeat("═", $innerW) . "╝{$reset}\n";
    }

    public function crear(string $tipo): void
    {
        $this->keyHandler->setRawMode(false);
        $this->keyHandler->clearStdin();
        $this->screen->clear();

        $tipoLabel = match($tipo) {
            'presupuesto' => 'PRESUPUESTO',
            'pedido' => 'PEDIDO',
            'albaran' => 'ALBARÁN',
            'factura' => 'FACTURA',
            'recibo' => 'RECIBO',
            default => strtoupper($tipo),
        };

        // DIBUJAR MARCO GLOBAL
        // Borde superior
        echo "\033[36m╔══════════════════════════════════════════════════════════════════════════════╗\n";
        // Título centrado
        $title = "NUEVO $tipoLabel";
        $titlePadding = (78 - mb_strlen($title)) / 2;
        echo "║" . str_repeat(" ", (int)floor($titlePadding)) . "\033[1;37m" . $title . "\033[36m" . str_repeat(" ", (int)ceil($titlePadding)) . "║\n";
        // Separador
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
        
        // Cuerpo vacío inicial para mantener el marco mientras se opera
        for ($i = 0; $i < 20; $i++) {
             echo "║" . str_repeat(" ", 78) . "║\n";
        }
        
        // Borde inferior
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\033[0m";
        
        // Mover cursor al inicio del área de contenido para los prompts
        // Como Laravel Prompts borra y redibuja, esto es un compromiso visual.
        // Lo ideal sería que el selector se renderice DENTRO del marco, pero Prompts toma control del stdout.
        // Simularemos el marco reimprimiéndolo o aceptando que el prompt desplazará cosas.
        // O mejor: Usamos el Window para dibujar y luego prompts en coordenadas si es posible (no fácil con Prompts).
        // SOLUCIÓN PRÁCTICA: Limpiar, dibujar título y dejar que Prompts use el área central, 
        // pero "enmarcándolo" visualmente antes de llamar a search.
        
        $this->screen->clear();
        echo "\033[36m╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║" . str_repeat(" ", (int)floor($titlePadding)) . "\033[1;37m" . $title . "\033[36m" . str_repeat(" ", (int)ceil($titlePadding)) . "║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            // Determinar si es Cliente o Proveedor según el tipo de documento
            $isCompra = str_contains($tipo, 'compra') || str_contains($tipo, 'pedido_compra'); 
            
            $apiTipo = $isCompra ? 'proveedor' : 'cliente';
            $humanLabel = $isCompra ? 'Proveedor' : 'Cliente';

            $tercerosResponse = $this->client->getTerceros(perPage: 100, tipo: $apiTipo);
            $terceros = [];
            foreach ($tercerosResponse['data'] ?? [] as $t) {
                $terceros[$t['id']] = $t['nombre_comercial'] . " (" . $t['nif_cif'] . ")";
            }

            if (empty($terceros)) {
                \Laravel\Prompts\error("No hay {$apiTipo}s registrados.");
                sleep(2);
                $this->keyHandler->setRawMode(true);
                return;
            }

            // Usar search
            $clienteId = \Laravel\Prompts\search(
                label: "Seleccione $humanLabel",
                options: fn (string $value) => 
                    strlen($value) === 0 
                        ? $terceros 
                        : array_filter($terceros, fn ($name) => str_contains(strtolower($name), strtolower($value))),
                placeholder: "Escriba para buscar...",
                scroll: 10
            );

            // 2. Añadir líneas
            $this->keyHandler->setRawMode(true);
            
            $lineas = [];
            $lineModal = new \App\SienteErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
            
            $continuarAñadiendo = true;
            
            while ($continuarAñadiendo) {
                // Mostrar resumen de líneas actuales (que ya dibuja su propio marco)
                $this->mostrarResumenLineas($lineas, $tipoLabel);
                
                $rawKey = $this->keyHandler->waitForKey();
                $key = \App\SienteErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
                
                if ($key === 'F5') {
                    $linea = $lineModal->run(function($searchText) {
                        return $this->client->searchProducto($searchText);
                    });
                    if ($linea) $lineas[] = $linea;
                } elseif ($key === 'F10') {
                    if (!empty($lineas)) $continuarAñadiendo = false;
                } elseif ($key === 'F12' || $key === 'ESC') {
                    $this->keyHandler->setRawMode(false);
                    return;
                }
            }

            if (empty($lineas)) {
                $this->keyHandler->setRawMode(false);
                \Laravel\Prompts\warning('Documento cancelado (sin líneas).');
                sleep(1);
            } else {
                $this->keyHandler->setRawMode(false);
                $confirm = \Laravel\Prompts\confirm('¿Desea crear el documento?', default: true);

                if ($confirm) {
                    $data = [
                        'tipo' => $tipo,
                        'tercero_id' => $clienteId,
                        'fecha' => date('Y-m-d'),
                        'lineas' => $lineas
                    ];

                    $this->client->createDocumento($data);
                    \Laravel\Prompts\info('Documento creado correctamente.');
                    sleep(1);
                }
            }
        } catch (\Exception $e) {
            $this->keyHandler->setRawMode(false);
            // El error limpio ya viene del ErpClient
            \Laravel\Prompts\error($e->getMessage());
            echo "\nPresione cualquier tecla para continuar...";
            $this->keyHandler->setRawMode(true);
            $this->keyHandler->waitForKey();
        }

        $this->keyHandler->setRawMode(true);
    }
    
    /**
     * Muestra un resumen de las líneas añadidas
     */
    private function mostrarResumenLineas(array $lineas, string $tipoLabel): void
    {
        $this->screen->clear();
        $width = 80;
        $innerW = $width - 2;
        
        $borderCol = $this->screen->color('border');
        $titleCol = $this->screen->color('title');
        $textCol = $this->screen->color('text');
        $highlightCol = $this->screen->color('highlight');
        $reset = $this->screen->reset('form_bg');

        // HEADER
        echo "{$borderCol}╔" . str_repeat("═", $innerW) . "╗\n";
        
        $title = "NUEVO $tipoLabel - LÍNEAS";
        $titleLen = mb_strlen($title);
        $padLeft = (int)floor(($innerW - $titleLen) / 2);
        $padRight = $innerW - $titleLen - $padLeft;
        
        echo "║" . str_repeat(" ", $padLeft) . "{$titleCol}" . $title . "{$borderCol}" . str_repeat(" ", $padRight) . "║\n";
        echo "╠" . str_repeat("═", $innerW) . "╣{$reset}\n";
        
        // BODY
        $linesArr = [];
        
        if (empty($lineas)) {
            $linesArr[] = "";
            $linesArr[] = "  {$highlightCol}No hay líneas añadidas todavía{$reset}";
            $linesArr[] = "";
        } else {
            // Header table
            $linesArr[] = "  {$borderCol}" .
                str_pad("Producto", 40) .
                str_pad("Cant.", 10, " ", STR_PAD_LEFT) .
                str_pad("Precio", 12, " ", STR_PAD_LEFT) .
                str_pad("Total", 12, " ", STR_PAD_LEFT) .
                "{$reset}";
            $linesArr[] = "  {$borderCol}" . str_repeat("─", $innerW - 4) . "{$reset}";
            
            $totalGeneral = 0;
            
            foreach ($lineas as $linea) {
                $total = $linea['cantidad'] * $linea['precio_unitario'];
                if (isset($linea['descuento']) && $linea['descuento'] > 0) {
                    $total *= (1 - $linea['descuento'] / 100);
                }
                $totalGeneral += $total;
                
                $cantidadStr = number_format($linea['cantidad'], 2, ',', '.');
                $precioStr = number_format($linea['precio_unitario'], 2, ',', '.') . ' €';
                $totalStr = number_format($total, 2, ',', '.') . ' €';
                
                $linesArr[] = "  {$textCol}" .
                    str_pad(substr($linea['descripcion'], 0, 38), 40) .
                    str_pad($cantidadStr, 10, " ", STR_PAD_LEFT) .
                    str_pad($precioStr, 12, " ", STR_PAD_LEFT) .
                    str_pad($totalStr, 12, " ", STR_PAD_LEFT) .
                    "{$reset}";
            }
            
            $linesArr[] = "  {$borderCol}" . str_repeat("─", $innerW - 4) . "{$reset}";
            
            $totalLabel = "TOTAL:";
            $totalVal = number_format($totalGeneral, 2, ',', '.') . ' €';
            $padLen = $innerW - 4 - mb_strlen($totalLabel) - 12; 
            
            $linesArr[] = "  {$this->screen->color('success')}" . $totalLabel . str_repeat(" ", max(0, $padLen)) . str_pad($totalVal, 12, " ", STR_PAD_LEFT) . "{$reset}";
        }
        
        while(count($linesArr) < 15) {
            $linesArr[] = "";
        }
        
        // Render Body
        foreach ($linesArr as $l) {
             $clean = preg_replace('/\033\[[0-9;:]*[mK]/', '', $l);
             $visibleLen = mb_strwidth($clean);
             $padding = max(0, $innerW - $visibleLen);
             echo "{$borderCol}║{$reset}" . $l . str_repeat(" ", $padding) . "{$borderCol}║{$reset}\n";
        }
        
        // FOOTER
        echo "{$borderCol}╠" . str_repeat("═", $innerW) . "╣{$reset}\n";
        $fKey = $this->screen->color('function_key');
        $helpText = "{$fKey}F5{$reset}={$textCol}Añadir  {$fKey}F10{$reset}={$textCol}Terminar  {$fKey}F12{$reset}={$textCol}Cancelar";
        $helpClean = preg_replace('/\033\[[0-9;:]*[mK]/', '', $helpText);
        $helpLen = mb_strwidth($helpClean);
        $helpPad = max(0, $innerW - 1 - $helpLen);
        
        echo "{$borderCol}║ {$reset}" . $helpText . str_repeat(" ", (int)$helpPad) . "{$borderCol}║{$reset}\n";
        echo "{$borderCol}╚" . str_repeat("═", $innerW) . "╝{$reset}\n";
    }

    /**
     * Muestra un mensaje al usuario
     */
    private function showMessage(string $message, string $type = 'info'): void
    {
        $this->screen->clear();
        
        $color = match($type) {
            'success' => "\033[32m",
            'error' => "\033[31m",
            'warning' => "\033[33m",
            default => "\033[37m"
        };
        
        $icon = match($type) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            default => 'ℹ'
        };
        
        echo "\n\n";
        echo "  {$color}{$icon} {$message}\033[0m\n\n";
        echo "  Presione cualquier tecla para continuar...";
        
        $this->keyHandler->waitForKey();
    }

    /**
     * Métodos específicos por tipo de documento
     */
    public function listarPresupuestos(): void
    {
        $this->listar('presupuesto');
    }

    public function listarPedidos(): void
    {
        $this->listar('pedido');
    }

    public function listarAlbaranes(): void
    {
        $this->listar('albaran');
    }

    public function listarFacturas(): void
    {
        $this->listar('factura');
    }

    public function listarRecibos(): void
    {
        $this->listar('recibo');
    }

    /**
     * Métodos específicos para Compras
     */
    public function listarPedidosCompra(): void
    {
        $this->listar('pedido_compra');
    }

    public function listarAlbaranesCompra(): void
    {
        $this->listar('albaran_compra');
    }

    public function listarFacturasCompra(): void
    {
        $this->listar('factura_compra');
    }

    public function listarRecibosCompra(): void
    {
        $this->listar('recibo_compra');
    }
}
