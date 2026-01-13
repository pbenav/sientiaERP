<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Display\ListController;
use App\NexErpTui\Display\FormController;
use App\NexErpTui\Input\KeyHandler;

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
            'pedido' => 'Pedidos',
            'albaran' => 'Albaranes',
            'factura' => 'Facturas',
            'recibo' => 'Recibos',
        ];

        $listController = new ListController($this->keyHandler, $this->screen, $tipoLabels[$tipo] ?? ucfirst($tipo));
        
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
            
            while (true) {
                // Renderizar pantalla completa
                $this->renderDocumentoEditor($doc, $fecha, $lineas, $editandoCabecera, $selectedLineIndex);
                
                $rawKey = $this->keyHandler->waitForKey();
                $key = \App\NexErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
                
                // Cambiar entre cabecera y líneas
                if ($key === 'TAB') {
                    $editandoCabecera = !$editandoCabecera;
                    if (!$editandoCabecera && empty($lineas)) {
                        $selectedLineIndex = -1; // Modo "añadir primera línea"
                    }
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
                // Editar cabecera
                elseif ($editandoCabecera) {
                    if ($key === 'BACKSPACE' || $key === 'DELETE') {
                        if (mb_strlen($fecha) > 0) {
                            $fecha = mb_substr($fecha, 0, -1);
                        }
                    } elseif (strlen($key) === 1 && (is_numeric($key) || $key === '-')) {
                        if (mb_strlen($fecha) < 10) {
                            $fecha .= $key;
                        }
                    }
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
                        $lineModal = new \App\NexErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
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
                        $lineModal = new \App\NexErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
                        
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
    private function renderDocumentoEditor(array $doc, string $fecha, array $lineas, bool $editandoCabecera, int $selectedLineIndex): void
    {
        $this->screen->clear();
        
        echo "\033[36m╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                        EDITAR DOCUMENTO                                      ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\033[0m\n\n";
        
        // CABECERA
        $cabeceraColor = $editandoCabecera ? "\033[1;33m" : "\033[37m";
        echo "  {$cabeceraColor}CABECERA:\033[0m\n";
        echo "  \033[36m" . str_repeat("─", 76) . "\033[0m\n";
        echo "  \033[37mNúmero:\033[0m {$doc['numero']}\n";
        echo "  {$cabeceraColor}Fecha:\033[0m {$fecha}" . ($editandoCabecera ? "_" : "") . "\n";
        echo "  \033[37mCliente:\033[0m {$doc['tercero']['nombre_comercial']}\n";
        echo "  \033[37mEstado:\033[0m " . ucfirst($doc['estado'] ?? 'borrador') . "\n\n";
        
        // LÍNEAS
        $lineasColor = !$editandoCabecera ? "\033[1;33m" : "\033[37m";
        echo "  {$lineasColor}LÍNEAS:\033[0m\n";
        echo "  \033[36m" . str_repeat("─", 76) . "\033[0m\n";
        
        if (empty($lineas)) {
            echo "  \033[33mNo hay líneas. Presione F5 para añadir.\033[0m\n";
        } else {
            // Cabecera de tabla con espaciado correcto
            echo "  \033[36m";
            echo str_pad("Producto", 38);  // 38 caracteres para producto
            echo str_pad("Cant.", 12, " ", STR_PAD_LEFT);   // 12 para cantidad
            echo str_pad("Precio", 14, " ", STR_PAD_LEFT);  // 14 para precio
            echo str_pad("Total", 14, " ", STR_PAD_LEFT);   // 14 para total
            echo "\033[0m\n";
            
            $totalGeneral = 0;
            foreach ($lineas as $index => $linea) {
                $isSelected = (!$editandoCabecera && $index === $selectedLineIndex);
                $prefix = $isSelected ? "\033[1;33m► " : "  ";
                $color = $isSelected ? "\033[1;33m" : "\033[37m";
                
                $cantidad = $linea['cantidad'] ?? 0;
                $precio = $linea['precio_unitario'] ?? 0;
                $descuento = $linea['descuento'] ?? 0;
                $total = $cantidad * $precio * (1 - $descuento / 100);
                $totalGeneral += $total;
                
                // Formatear números con alineación decimal
                $cantidadStr = number_format($cantidad, 2, ',', '.');
                
                // Precio y Total con alineación decimal (€ alineado a la derecha)
                $precioNum = number_format($precio, 2, ',', '.');
                $totalNum = number_format($total, 2, ',', '.');
                
                // Alinear números a la derecha, dejando espacio para €
                $precioStr = str_pad($precioNum, 11, " ", STR_PAD_LEFT) . ' €';
                $totalStr = str_pad($totalNum, 11, " ", STR_PAD_LEFT) . ' €';
                
                echo $prefix . $color;
                // Producto (truncar si es muy largo)
                echo str_pad(mb_substr($linea['descripcion'] ?? '', 0, 35), 38);
                // Cantidad (alineada a la derecha)
                echo str_pad($cantidadStr, 12, " ", STR_PAD_LEFT);
                // Precio (número alineado + €)
                echo $precioStr . ' ';
                // Total (número alineado + €)
                echo $totalStr;
                echo "\033[0m\n";
            }
            
            // Línea separadora y total
            echo "  \033[36m" . str_repeat("─", 76) . "\033[0m\n";
            
            // Total general con misma alineación
            $totalGeneralNum = number_format($totalGeneral, 2, ',', '.');
            $totalGeneralStr = str_pad($totalGeneralNum, 11, " ", STR_PAD_LEFT) . ' €';
            
            echo "  \033[1;32m";
            echo str_pad("TOTAL:", 64, " ", STR_PAD_LEFT);
            echo $totalGeneralStr;
            echo "\033[0m\n";
        }
        
        echo "\n";
        echo "\033[36m╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║ \033[0m";
        
        if ($editandoCabecera) {
            $helpText = "\033[32mTAB\033[0m=Ir a Líneas  \033[32mF10\033[0m=Guardar  \033[32mF12\033[0m=Cancelar";
        } else {
            $helpText = "\033[32mF5\033[0m=Añadir  \033[32mF2\033[0m=Editar  \033[32mF8\033[0m=Eliminar  \033[32mTAB\033[0m=Cabecera  \033[32mF10\033[0m=Guardar  \033[32mF12\033[0m=Cancelar";
        }
        
        // Calcular padding para la barra de ayuda
        $helpTextPlain = preg_replace('/\033\[[0-9;]*m/', '', $helpText);
        $helpTextLength = mb_strlen($helpTextPlain);
        $padding = 76 - $helpTextLength;
        
        echo $helpText;
        echo str_repeat(" ", max(0, $padding));
        echo " \033[36m║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\033[0m\n";
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

        echo "\033[36m╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   NUEVO $tipoLabel                            ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

        try {
            // 1. Seleccionar Cliente
            $tercerosResponse = $this->client->getTerceros(perPage: 100, tipo: 'cliente');
            $clientes = [];
            foreach ($tercerosResponse['data'] ?? [] as $t) {
                $clientes[$t['id']] = $t['nombre_comercial'] . " (" . $t['nif_cif'] . ")";
            }

            if (empty($clientes)) {
                \Laravel\Prompts\error('No hay clientes registrados.');
                sleep(2);
                $this->keyHandler->setRawMode(true);
                return;
            }

            $clienteId = \Laravel\Prompts\select('Seleccione Cliente', options: $clientes, required: true);

            // 2. Añadir líneas con el nuevo modal
            $this->keyHandler->setRawMode(true);
            
            $lineas = [];
            $lineModal = new \App\NexErpTui\Display\LineItemModal($this->keyHandler, $this->screen);
            
            $continuarAñadiendo = true;
            
            while ($continuarAñadiendo) {
                // Mostrar resumen de líneas actuales
                $this->mostrarResumenLineas($lineas, $tipoLabel);
                
                echo "\n  \033[37mPresione \033[32mF5\033[37m para añadir línea, \033[32mF10\033[37m para finalizar, \033[32mF12\033[37m para cancelar\033[0m\n";
                
                $rawKey = $this->keyHandler->waitForKey();
                $key = \App\NexErpTui\Input\FunctionKeyMapper::mapKey($rawKey);
                
                if ($key === 'F5') {
                    // Añadir nueva línea
                    $linea = $lineModal->run(function($searchText) {
                        return $this->client->searchProducto($searchText);
                    });
                    
                    if ($linea) {
                        $lineas[] = $linea;
                    }
                } elseif ($key === 'F10') {
                    // Finalizar
                    if (!empty($lineas)) {
                        $continuarAñadiendo = false;
                    }
                } elseif ($key === 'F12' || $key === 'ESC') {
                    // Cancelar
                    $this->keyHandler->setRawMode(false);
                    return;
                }
            }

            if (empty($lineas)) {
                $this->keyHandler->setRawMode(false);
                \Laravel\Prompts\warning('Documento cancelado (sin líneas).');
                sleep(1);
            } else {
                // Confirmar creación
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
            \Laravel\Prompts\error('Error al crear documento: ' . $e->getMessage());
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
        
        echo "\033[36m╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        
        $title = "NUEVO $tipoLabel - LÍNEAS";
        $titleLength = mb_strlen($title);
        $availableSpace = 79 - 4;
        $totalPadding = $availableSpace - $titleLength;
        $leftPadding = (int)floor($totalPadding / 2) + 1;
        $rightPadding = (int)ceil($totalPadding / 2) + 1;
        
        echo "║" . str_repeat(" ", $leftPadding);
        echo "\033[1;37m" . $title . "\033[36m";
        echo str_repeat(" ", $rightPadding) . "║\n";
        
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\033[0m\n\n";
        
        if (empty($lineas)) {
            echo "  \033[33mNo hay líneas añadidas todavía\033[0m\n";
        } else {
            echo "  \033[36m";
            echo str_pad("Producto", 40);
            echo str_pad("Cant.", 10, " ", STR_PAD_LEFT);
            echo str_pad("Precio", 12, " ", STR_PAD_LEFT);
            echo str_pad("Total", 12, " ", STR_PAD_LEFT);
            echo "\033[0m\n";
            echo "  \033[36m" . str_repeat("─", 74) . "\033[0m\n";
            
            $totalGeneral = 0;
            
            foreach ($lineas as $linea) {
                $total = $linea['cantidad'] * $linea['precio_unitario'];
                if (isset($linea['descuento']) && $linea['descuento'] > 0) {
                    $total *= (1 - $linea['descuento'] / 100);
                }
                $totalGeneral += $total;
                
                echo "  \033[37m";
                echo str_pad(substr($linea['descripcion'], 0, 38), 40);
                echo str_pad(number_format($linea['cantidad'], 2, ',', '.'), 10, " ", STR_PAD_LEFT);
                echo str_pad(number_format($linea['precio_unitario'], 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
                echo str_pad(number_format($total, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
                echo "\033[0m\n";
            }
            
            echo "  \033[36m" . str_repeat("─", 74) . "\033[0m\n";
            echo "  \033[1;32m" . str_pad("TOTAL:", 62, " ", STR_PAD_LEFT);
            echo str_pad(number_format($totalGeneral, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
            echo "\033[0m\n";
        }
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
}
