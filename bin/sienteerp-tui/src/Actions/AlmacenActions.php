<?php

namespace App\SienteErpTui\Actions;

use App\SienteErpTui\ErpClient;
use App\SienteErpTui\Display\Screen;
use App\SienteErpTui\Display\ListController;
use App\SienteErpTui\Display\FormController;
use App\SienteErpTui\Input\KeyHandler;

class AlmacenActions
{
    private ErpClient $client;
    private Screen $screen;
    private KeyHandler $keyHandler;

    public function __construct(ErpClient $client, Screen $screen, KeyHandler $keyHandler)
    {
        $this->client = $client;
        $this->screen = $screen;
        $this->keyHandler = $keyHandler;
    }

    public function listarStock(): void
    {
        $listController = new ListController($this->keyHandler, $this->screen, 'STOCK DE ALMACÉN');
        
        // Definir columnas
        $listController->setColumns([
            ['field' => 'sku', 'label' => 'SKU', 'width' => 15],
            ['field' => 'name', 'label' => 'Producto', 'width' => 40],
            ['field' => 'price', 'label' => 'Precio', 'width' => 12, 'format' => 'currency'],
            ['field' => 'tax_rate', 'label' => 'IVA', 'width' => 8, 'format' => 'percentage'],
        ]);
        
        // Callback para obtener datos
        $listController->onFetch(function($page, $perPage) {
            return $this->client->getProductos($page, $perPage);
        });
        
        // Callback para crear
        $listController->onCreate(function() {
            $this->crear();
        });
        
        // Callback para editar
        $listController->onEdit(function($producto) {
            $this->editar($producto['id']);
        });
        
        // Ejecutar
        $listController->run();
    }

    public function crear(): void
    {
        $form = new FormController($this->keyHandler, $this->screen, 'NUEVO PRODUCTO');
        
        // Añadir campos
        $form->addField('sku', 'SKU', '', required: true)
             ->addField('name', 'Nombre del Producto', '', required: true)
             ->addField('description', 'Descripción', '')
             ->addField('price', 'Precio (€)', '', required: true, validator: function($value) {
                 if (!is_numeric($value)) {
                     return 'Debe ser un número';
                 }
                 return null;
             })
             ->addField('stock', 'Stock Inicial', '0', required: false, validator: function($value) {
                 if (!is_numeric($value)) {
                     return 'Debe ser numérico';
                 }
                 return null;
             })
             ->addField('tax_rate', 'IVA %', '21', required: false, validator: function($value) {
                 if (!is_numeric($value)) {
                     return 'Debe ser numérico';
                 }
                 return null;
             });
        
        // Ejecutar formulario
        $values = $form->run();
        
        if ($values) {
            try {
                $data = [
                    'sku' => $values['sku'],
                    'name' => $values['name'],
                    'description' => $values['description'],
                    'price' => (float)$values['price'],
                    'stock' => (int)$values['stock'],
                    'tax_rate' => (float)$values['tax_rate'],
                    'barcode' => time() // Generar uno simple por defecto
                ];
                
                $this->client->createProducto($data);
                
                $this->showMessage('Producto creado correctamente', 'success');
            } catch (\Exception $e) {
                $this->showMessage('Error al crear producto: ' . $e->getMessage(), 'error');
            }
        }
    }

    public function editar(int $id): void
    {
        try {
            $producto = $this->client->getProducto($id);
            
            $form = new FormController($this->keyHandler, $this->screen, 'EDITAR PRODUCTO');
            
            // Añadir campos con valores actuales
            $form->addField('sku', 'SKU', $producto['sku'] ?? '', required: true)
                 ->addField('name', 'Nombre', $producto['name'] ?? '', required: true)
                 ->addField('description', 'Descripción', $producto['description'] ?? '')
                 ->addField('price', 'Precio (€)', (string)($producto['price'] ?? 0), required: true, validator: function($value) {
                     if (!is_numeric($value)) {
                         return 'Debe ser un número';
                     }
                     return null;
                 })
                 ->addField('stock', 'Stock', (string)($producto['stock'] ?? 0), required: false, validator: function($value) {
                     if (!is_numeric($value)) {
                         return 'Debe ser numérico';
                     }
                     return null;
                 })
                 ->addField('tax_rate', 'IVA %', (string)($producto['tax_rate'] ?? 21), required: false, validator: function($value) {
                     if (!is_numeric($value)) {
                         return 'Debe ser numérico';
                     }
                     return null;
                 });
            
            // Ejecutar formulario
            $values = $form->run();
            
            if ($values) {
                try {
                    $data = [
                        'sku' => $values['sku'],
                        'name' => $values['name'],
                        'description' => $values['description'],
                        'price' => (float)$values['price'],
                        'stock' => (int)$values['stock'],
                        'tax_rate' => (float)$values['tax_rate'],
                    ];
                    
                    $this->client->updateProducto($id, $data);
                    
                    $this->showMessage('Producto actualizado correctamente', 'success');
                } catch (\Exception $e) {
                    $this->showMessage('Error al actualizar: ' . $e->getMessage(), 'error');
                }
            }
        } catch (\Exception $e) {
            $this->showMessage('Error al cargar producto: ' . $e->getMessage(), 'error');
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
}
