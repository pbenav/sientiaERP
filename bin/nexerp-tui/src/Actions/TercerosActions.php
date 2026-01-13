<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Display\ListController;
use App\NexErpTui\Display\FormController;
use App\NexErpTui\Input\KeyHandler;

class TercerosActions
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
     * Listar todos los terceros
     */
    public function listar(?string $tipo = null): void
    {
        $tipoLabel = $tipo ? ucfirst($tipo) . 's' : 'Todos los Terceros';
        
        $listController = new ListController($this->keyHandler, $this->screen, $tipoLabel);
        
        // Definir columnas
        $listController->setColumns([
            ['field' => 'codigo', 'label' => 'Código', 'width' => 12],
            ['field' => 'nombre_comercial', 'label' => 'Nombre', 'width' => 35],
            ['field' => 'nif_cif', 'label' => 'NIF/CIF', 'width' => 15],
            ['field' => 'tipos_str', 'label' => 'Tipos', 'width' => 20],
        ]);
        
        // Callback para obtener datos
        $listController->onFetch(function($page, $perPage) use ($tipo) {
            $response = $this->client->getTerceros($page, $perPage, $tipo);
            
            // Procesar datos para mostrar tipos como string
            $data = $response['data'] ?? [];
            foreach ($data as &$item) {
                $tipos = array_column($item['tipos'] ?? [], 'nombre');
                $item['tipos_str'] = implode(', ', $tipos);
            }
            
            return [
                'data' => $data,
                'total' => $response['total'] ?? 0,
                'last_page' => $response['last_page'] ?? 1
            ];
        });
        
        // Callback para crear
        $listController->onCreate(function() use ($tipo) {
            $this->crear($tipo);
        });
        
        // Callback para editar
        $listController->onEdit(function($tercero) {
            $this->editar($tercero['id']);
        });
        
        // Callback para ver detalles
        $listController->onView(function($tercero) {
            $this->details->renderTercero($tercero['id']);
        });
        
        // Ejecutar
        $listController->run();
    }

    public function crear(?string $tipo = null): void
    {
        $form = new FormController($this->keyHandler, $this->screen, 'NUEVO TERCERO');
        
        // Añadir campos
        $form->addField('nombre_comercial', 'Nombre Comercial/Razón Social', '', true)
             ->addField('nif_cif', 'NIF/CIF', '', true, function($value) {
                 // Validación básica de NIF/CIF
                 if (strlen($value) < 8) {
                     return 'NIF/CIF debe tener al menos 8 caracteres';
                 }
                 return null;
             })
             ->addField('email', 'Email', '', false, function($value) {
                 if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                     return 'Email inválido';
                 }
                 return null;
             })
             ->addField('telefono', 'Teléfono', '');
        
        // Ejecutar formulario
        $values = $form->run();
        
        if ($values) {
            try {
                // Determinar tipos
                $tipos = [];
                if ($tipo) {
                    $tipos[] = strtoupper(substr($tipo, 0, 3));
                } else {
                    $tipos[] = 'CLI'; // Por defecto cliente
                }
                
                $data = [
                    'nombre_comercial' => $values['nombre_comercial'],
                    'razon_social' => $values['nombre_comercial'],
                    'nif_cif' => $values['nif_cif'],
                    'email' => $values['email'],
                    'telefono' => $values['telefono'],
                    'tipos' => $tipos
                ];
                
                $this->client->createTercero($data);
                
                // Mostrar mensaje de éxito
                $this->showMessage('Tercero creado correctamente', 'success');
            } catch (\Exception $e) {
                $this->showMessage('Error al crear tercero: ' . $e->getMessage(), 'error');
            }
        }
    }

    public function editar(int $id): void
    {
        try {
            $tercero = $this->client->getTercero($id);
            
            $form = new FormController($this->keyHandler, $this->screen, 'EDITAR TERCERO');
            
            // Añadir campos con valores actuales
            $form->addField('nombre_comercial', 'Nombre Comercial', $tercero['nombre_comercial'] ?? '', true)
                 ->addField('nif_cif', 'NIF/CIF', $tercero['nif_cif'] ?? '', true, function($value) {
                     if (strlen($value) < 8) {
                         return 'NIF/CIF debe tener al menos 8 caracteres';
                     }
                     return null;
                 })
                 ->addField('email', 'Email', $tercero['email'] ?? '', false, function($value) {
                     if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                         return 'Email inválido';
                     }
                     return null;
                 })
                 ->addField('telefono', 'Teléfono', $tercero['telefono'] ?? '');
            
            // Ejecutar formulario
            $values = $form->run();
            
            if ($values) {
                try {
                    // Mantener tipos existentes
                    $tiposActuales = array_map(fn($t) => $t['codigo'], $tercero['tipos'] ?? []);
                    
                    $data = [
                        'nombre_comercial' => $values['nombre_comercial'],
                        'razon_social' => $values['nombre_comercial'],
                        'nif_cif' => $values['nif_cif'],
                        'email' => $values['email'],
                        'telefono' => $values['telefono'],
                        'tipos' => !empty($tiposActuales) ? $tiposActuales : ['CLI']
                    ];
                    
                    $this->client->updateTercero($id, $data);
                    
                    $this->showMessage('Tercero actualizado correctamente', 'success');
                } catch (\Exception $e) {
                    $this->showMessage('Error al actualizar: ' . $e->getMessage(), 'error');
                }
            }
        } catch (\Exception $e) {
            $this->showMessage('Error al cargar tercero: ' . $e->getMessage(), 'error');
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
     * Listar solo clientes
     */
    public function listarClientes(): void
    {
        $this->listar('cliente');
    }

    /**
     * Listar solo proveedores
     */
    public function listarProveedores(): void
    {
        $this->listar('proveedor');
    }
}
