<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Product;
use App\Models\Ticket;
use Filament\Notifications\Notification;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.ticket-resource.pages.create-ticket';

    protected ?string $heading = '';

    // Propiedades para la gestión del POS
    public $tpvActivo = 1;
    public $ticket; // Modelo Ticket actual
    
    // Inputs de Línea
    public $nuevoCodigo = '';
    public $nuevoNombre = ''; // Búsqueda auxiliar
    public $nuevoCantidad = 1;
    public $nuevoProducto = null; // Modelo Product
    public $nuevoPrecio = 0;
    public $nuevoDescuento = 0;
    public $nuevoImporte = 0;
    
    // Totales Calculados
    public $lineas = []; // Array visual
    public $total = 0;
    public $subtotal = 0;
    public $impuestos = 0;
    public $entrega = 0;

    public function mount(): void
    {
        // No llamamos parent::mount() porque gestionamos el registro manualmente
        $this->form->fill();
        $this->cargarTpv(1);
    }
    
    // Cargar un slot de TPV específico
    public function cargarTpv($slot)
    {
        $this->tpvActivo = $slot;
        
        // Buscar ticket abierto para este TPV SLOT
        $this->ticket = Ticket::firstOrCreate(
            [
                'tpv_slot' => $slot,
                'status' => 'open'
            ],
            [
                'user_id' => auth()->id(),
                'session_id' => (string) \Illuminate\Support\Str::uuid(), // UUID único global
                'numero' => 'BORRADOR', // Se asignará número real al cerrar
                'created_at' => now(),
            ]
        );
        
        // Cargar líneas del ticket
        $this->lineas = $this->ticket->items()->get()->map(function($item) {
            return [
                'product_id' => $item->product_id,
                'codigo' => $item->product->sku ?? '---',
                'nombre' => $item->product->name,
                'cantidad' => $item->quantity,
                'precio' => $item->unit_price,
                'descuento' => 0, // TODO: Añadir descuento a TicketItem si falta
                'importe' => $item->total,
            ];
        })->toArray();
        
        $this->recalcularTotales();
        
        // Rellenar datos de cabecera
        $this->data['fecha'] = $this->ticket->created_at->format('Y-m-d');
        $this->data['numero'] = $this->ticket->id; // Mostrar ID interno o "BORRADOR"
        $this->form->fill([
            'customer_id' => $this->ticket->customer_id,
        ]);
        
        // Limpiar inputs entrada
        $this->limpiarInputs();
    }
    
    public function cambiarTpv($slot)
    {
        if ($this->tpvActivo === $slot) return;
        
        // Guardar estado actual antes de cambiar (aunque ya se guarda al anotar)
        // Lo hacemos por si ha cambiado cliente o fecha
        $this->guardarCabecera();
        
        $this->cargarTpv($slot);
    }
    
    public function guardarCabecera()
    {
        $datos = $this->form->getState();
        $this->ticket->customer_id = $datos['customer_id'] ?? null;
        // $this->ticket->created_at = ...
        $this->ticket->save();
    }

    // Método para buscar producto por código o nombre
    public function buscarProducto()
    {
        // Si hay código, buscar por SKU/Barcode
        if (!empty($this->nuevoCodigo)) {
            $producto = Product::where('sku', $this->nuevoCodigo)->orWhere('barcode', $this->nuevoCodigo)->first();
            
            if ($producto) {
                $this->cargarProducto($producto);
                return;
            }
        }
        
        // Si no hay código pero hay nombre
        if (!empty($this->nuevoNombre)) {
            $producto = Product::where('name', 'like', "%{$this->nuevoNombre}%")->first();
            
            if ($producto) {
                $this->cargarProducto($producto);
                return;
            }
        }

        Notification::make()->title('Producto no encontrado')->warning()->send();
        $this->limpiarInputs();
    }

    protected function cargarProducto($producto)
    {
        $this->nuevoProducto = $producto;
        $this->nuevoCodigo = $producto->sku; 
        $this->nuevoNombre = $producto->name; 
        $this->nuevoPrecio = $producto->price;
        $this->nuevoCantidad = 1;
        $this->calcularImporteLinea();
    }
    
    public function calcularImporteLinea()
    {
        $base = $this->nuevoCantidad * $this->nuevoPrecio;
        $this->nuevoImporte = $base * (1 - ($this->nuevoDescuento / 100));
    }

    public function updatedNuevoCantidad() { $this->calcularImporteLinea(); }
    public function updatedNuevoPrecio() { $this->calcularImporteLinea(); }
    public function updatedNuevoDescuento() { $this->calcularImporteLinea(); }
    
    public function anotarLinea()
    {
        if (!$this->nuevoProducto && empty($this->nuevoCodigo)) return;
        
        // Añadir a memoria
        $linea = [
            'product_id' => $this->nuevoProducto->id,
            'codigo' => $this->nuevoProducto->sku,
            'nombre' => $this->nuevoProducto->name,
            'cantidad' => $this->nuevoCantidad,
            'precio' => $this->nuevoPrecio,
            'descuento' => $this->nuevoDescuento,
            'importe' => $this->nuevoImporte,
        ];
        
        $this->lineas[] = $linea;
        
        // Persistir en BDD
        $this->persistirLinea($linea);
        
        $this->recalcularTotales();
        $this->limpiarInputs();
        
        // Emitir evento para foco?
    }
    
    protected function persistirLinea($linea)
    {
        // Crear TicketItem
        try {
            $this->ticket->items()->create([
                'product_id' => $linea['product_id'],
                'quantity' => $linea['cantidad'],
                'unit_price' => $linea['precio'],
                'tax_rate' => 21, // TODO: Obtener del producto dinámicamente
                'subtotal' => $linea['importe'] / 1.21, // Base imponible aproximada
                'tax_amount' => $linea['importe'] - ($linea['importe'] / 1.21),
                'total' => $linea['importe'],
            ]);
            $this->ticket->recalculateTotals();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('POS Error Save Item: '.$e->getMessage());
            Notification::make()->title('Error guardando línea: ' . $e->getMessage())->danger()->send();
        }
    }
    
    public function eliminarLinea($index)
    {
        // Obtener el item correspondiente en BDD (asumiendo orden, arriesgado pero vale por ahora)
        // Mejor sería traer IDs reales de items
        // Para simplificar, borramos todo y recreamos o usamos ID si lo tuviéramos
        
        // Estrategia Robustez: Recargar items desde BDD al inicio y mantener IDs
        // Como estamos en protitipado rápido, voy a borrar TODOS los items del ticket y recrearlos (ineficiente pero seguro)
        // O mejor: Borrar el item específico.
        // Vamos a RECARGAR desde BDD para tener IDs
        
        // HACK: Por ahora, solo memoria y save total
        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
        
        // Sincronización BRUTA (Borrar todo y repoblar) - Seguro para estado consistente
        $this->ticket->items()->delete();
        foreach($this->lineas as $l) {
            $this->persistirLinea($l);
        }
        
        $this->recalcularTotales();
    }
    
    public function recalcularTotales()
    {
        $this->total = collect($this->lineas)->sum('importe');
    }
    
    protected function limpiarInputs()
    {
        $this->nuevoCodigo = '';
        $this->nuevoNombre = '';
        $this->nuevoCantidad = 1;
        $this->nuevoPrecio = 0;
        $this->nuevoDescuento = 0;
        $this->nuevoImporte = 0;
        $this->nuevoProducto = null;
    }
    
    // Sobreescribir create para cerrar ticket
    public function create(bool $another = false): void
    {
        // Cerrar ticket actual
        $this->ticket->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total' => $this->total,
            'amount_paid' => $this->entrega,
            'change_given' => max(0, $this->entrega - $this->total),
        ]);
        
        Notification::make()->title('Ticket Finalizado')->success()->send();
        
        // Recargar TPV1 limpio
        $this->cargarTpv($this->tpvActivo);
    }

}
