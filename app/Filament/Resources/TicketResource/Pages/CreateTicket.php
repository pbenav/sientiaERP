<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\Page;
use Livewire\Component;
use Filament\Actions;
use Filament\Forms\Set; 
use App\Models\Tercero;
use App\Models\Product;
use App\Models\Ticket;
use Filament\Notifications\Notification;

class CreateTicket extends Page
{
    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.ticket-resource.pages.create-ticket';

    protected ?string $heading = '';

    // Propiedades para la gestión del POS
    public $tpvActivo = 1;
    public $ticket; // Modelo Ticket actual
    
    // FECHA - Propiedad dedicada para binding
    public $fecha;

    // Inputs de Línea
    public $nuevoCodigo = '';
    public $nuevoNombre = ''; // Búsqueda auxiliar
    public $nuevoCantidad = 1;
    public $nuevoProducto = null; // Modelo Product
    public $nuevoPrecio = 0;
    public $nuevoDescuento = 0;
    public $nuevoImporte = 0;
    
    // Listas de Autocompletado
    public $resultadosCodigo = [];
    public $resultadosNombre = [];
    public $resultadosClientes = []; // Nueva lista para clientes

    // Inputs de Cabecera
    public $nuevoClienteNombre = ''; // Input visual para cliente

    // Totales Calculados
    public $lineas = []; // Array visual
    public $total = 0;
    public $subtotal = 0;
    public $impuestos = 0;
    public $entrega = 0;
    public $payment_method = 'cash'; // Método de pago (cash, card)
    public $data = []; // Datos del formulario (necesario para Page)

    public function mount(): void
    {
        // No llamamos parent::mount() porque gestionamos el registro manualmente
        $this->form->fill();
        
        // Cargar primeros 10 clientes para dropdown inicial
        $this->resultadosClientes = Tercero::clientes()
                                    ->activos()
                                    ->orderBy('nombre_comercial')
                                    ->limit(10)
                                    ->pluck('nombre_comercial', 'id')
                                    ->toArray();
        
        // Cargar primeros productos para mostrar en datalist al hacer focus
        $this->cargarProductosIniciales();
        
        // INICIALIZAR FECHA A HOY
        $this->fecha = now()->format('Y-m-d');
        
        // Si viene ticket_id por parámetro, cargar ese ticket
        $ticketId = request()->query('ticket_id');
        if ($ticketId) {
            $ticketExistente = Ticket::find($ticketId);
            if ($ticketExistente) {
                $this->cargarTicketExistente($ticketExistente);
                return;
            }
        }
        
        $this->cargarTpv(1);
    }
    
    // Cargar un slot de TPV específico
    public function cargarTpv($slot)
    {
        $this->tpvActivo = $slot;
        
        // Intentar cargar ticket abierto para este slot
        $ticketExistente = Ticket::where('tpv_slot', $slot)
                                 ->where('status', 'open')
                                 ->first();
        
        $ticketExistia = (bool) $ticketExistente;
        
        $this->ticket = Ticket::firstOrCreate(
            [
                'tpv_slot' => $slot,
                'status' => 'open',
            ],
            [
                'user_id' => auth()->id(),
                'session_id' => (string) \Illuminate\Support\Str::uuid(),
                'numero' => 'BORRADOR',
                'created_at' => now(),
            ]
        );
        
        // IMPORTANTE: Asignar cliente por defecto si no tiene cliente asignado
        if (!$this->ticket->tercero_id) {
            $clientePorDefectoId = \App\Models\Setting::get('pos_default_tercero_id');
            if ($clientePorDefectoId) {
                $this->ticket->tercero_id = $clientePorDefectoId;
                $this->ticket->save();
                
                // Asegurarse de que el cliente por defecto esté en la lista de resultados
                $tercero = \App\Models\Tercero::find($clientePorDefectoId);
                if ($tercero && !isset($this->resultadosClientes[$clientePorDefectoId])) {
                    $this->resultadosClientes[$clientePorDefectoId] = $tercero->nombre_comercial;
                }
            }
        }
        
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
        
        // SIEMPRE establecer fecha a hoy
        $this->fecha = now()->format('Y-m-d');
        $this->data['fecha'] = $this->fecha;
        $this->data['numero'] = $this->ticket->id;
        
        // IMPORTANTE: Si hay cliente, asegurarse de que esté en la lista de resultados PRIMERO
        if ($this->ticket->tercero_id) {
            $cliente = \App\Models\Tercero::find($this->ticket->tercero_id);
            if ($cliente) {
                // Asegurarse de que el cliente está en la lista
                $this->resultadosClientes[$this->ticket->tercero_id] = $cliente->nombre_comercial;
                // Asignar el valor al select (convertir a string para que Livewire lo reconozca)
                $this->nuevoClienteNombre = (string) $this->ticket->tercero_id;
            }
        } else {
            $this->nuevoClienteNombre = '';
        }
        
        // Cargar cliente en el form
        $this->form->fill([
            'tercero_id' => $this->ticket->tercero_id,
        ]);
        
        // Limpiar inputs entrada
        $this->limpiarInputs();
    }
    
    public function cargarProductosIniciales()
    {
        // Cargar primeros 20 productos para mostrar en datalists
        $productos = Product::orderBy('name')
                            ->limit(20)
                            ->get();
        
        $this->resultadosCodigo = $productos->pluck('sku', 'id')->toArray();
        $this->resultadosNombre = $productos->pluck('name', 'id')->toArray();
    }
    
    public function cambiarTpv($slot)
    {
        if ($this->tpvActivo === $slot) return;
        
        // Guardar estado actual antes de cambiar (si hay ticket activo)
        if ($this->ticket) {
            $this->guardarCabecera();
        }
        
        $this->cargarTpv($slot);
    }
    
    public function guardarCabecera()
    {
        if (!$this->ticket) return;
        
        $datos = $this->form->getState();
        $this->ticket->tercero_id = $datos['tercero_id'] ?? null;
        // $this->ticket->created_at = ...
        $this->ticket->save();
    }

    // Buscador de Clientes
    public function updatedNuevoClienteNombre() 
    { 
        // Si es un ID numérico (cliente seleccionado), no hacer búsqueda
        // Solo guardar el cliente en el ticket
        if (is_numeric($this->nuevoClienteNombre) && $this->nuevoClienteNombre > 0) {
            $tercero = Tercero::find($this->nuevoClienteNombre);
            if ($tercero) {
                // Guardar el cliente en el ticket actual
                $this->ticket->tercero_id = $tercero->id;
                $this->ticket->save();
                
                // Asegurarse de que el cliente está en la lista
                if (!isset($this->resultadosClientes[$tercero->id])) {
                    $this->resultadosClientes[$tercero->id] = $tercero->nombre_comercial;
                }
            }
            return;
        }
        
        // Si está vacío, mostrar los primeros 10 ordenados
        if (empty($this->nuevoClienteNombre)) {
            $this->resultadosClientes = Tercero::clientes()
                                        ->activos()
                                        ->orderBy('nombre_comercial')
                                        ->limit(10)
                                        ->pluck('nombre_comercial', 'id')
                                        ->toArray();
        } elseif (strlen($this->nuevoClienteNombre) > 1) {
            $this->resultadosClientes = Tercero::clientes()
                                        ->activos()
                                        ->where('nombre_comercial', 'like', "%{$this->nuevoClienteNombre}%")
                                        ->limit(10)
                                        ->pluck('nombre_comercial', 'id')
                                        ->toArray();
        } else {
            $this->resultadosClientes = [];
        }
    }

    public function seleccionarCliente($force = false)
    {
        if (!empty($this->nuevoClienteNombre)) {
            // Si viene un ID (del select), buscar por ID
            $tercero = Tercero::find($this->nuevoClienteNombre);
            
            if ($tercero) {
                // Guardar el cliente en el ticket actual
                $this->ticket->tercero_id = $tercero->id;
                $this->ticket->save();
            } else {
                 if ($force) Notification::make()->title('Cliente no encontrado')->warning()->send();
            }
        }
    }
    
    /**
     * Convertir SKU a mayúsculas automáticamente y autocompletar
     */
    public function updatedNuevoCodigo($value)
    {
        // Convertir a mayúsculas
        $this->nuevoCodigo = strtoupper($value);
        
        // Autocompletar SKU si tiene al menos 1 carácter
        if (strlen($this->nuevoCodigo) >= 1) {
            $this->resultadosCodigo = Product::where('sku', 'like', "%{$this->nuevoCodigo}%")
                                        ->orWhere('barcode', 'like', "%{$this->nuevoCodigo}%")
                                        ->limit(20)
                                        ->pluck('sku', 'id')
                                        ->toArray();
        } else {
            $this->resultadosCodigo = [];
        }
    }

    // Método para buscar producto por código o nombre
    public function buscarProducto($force = false)
    {
        // Si hay código, buscar por SKU/Barcode
        if (!empty($this->nuevoCodigo)) {
            // Primero Exact Match
            $producto = Product::where('sku', $this->nuevoCodigo)->orWhere('barcode', $this->nuevoCodigo)->first();
            
            // Si no es exacto y es Force (Enter), buscar like (aunque SKU suele ser exacto)
            if (!$producto && $force) {
                // $producto = ... lógica adicional si se quisiera
            }

            if ($producto) {
                $this->cargarProducto($producto);
                return;
            }
        }
        
        // Si no hay código pero hay nombre
        if (!empty($this->nuevoNombre)) {
            // Priorizar coincidencia exacta primero (útil si viene de autocompletado o input completo)
            $producto = Product::where('name', $this->nuevoNombre)->first();
            
            // Solo si NO es exacto Y se ha forzado (Enter), hacemos búsqueda "fuzzy"
            if (!$producto && $force) {
                $producto = Product::where('name', 'like', "%{$this->nuevoNombre}%")->first();
            }
            
            if ($producto) {
                $this->cargarProducto($producto);
                return;
            }
        }

        // Solo notificar error si fue una acción forzada (Enter) Y había algo escrito
        if ($force && (!empty($this->nuevoCodigo) || !empty($this->nuevoNombre))) {
            Notification::make()->title('Producto no encontrado')->warning()->send();
            $this->limpiarInputs();
        }
    }

    protected function cargarProducto($producto)
    {
        $this->nuevoProducto = $producto;
        $this->nuevoCodigo = $producto->sku; 
        $this->nuevoNombre = $producto->name; 
        $this->nuevoPrecio = $producto->price;
        $this->nuevoCantidad = 1;
        $this->calcularImporteLinea();
        
        // Emitir evento para mover foco a cantidad
        $this->dispatch('focus-cantidad');
    }
    
    public function calcularImporteLinea()
    {
        $base = $this->nuevoCantidad * $this->nuevoPrecio;
        $this->nuevoImporte = $base * (1 - ($this->nuevoDescuento / 100));
    }

    public function updatedNuevoNombre() 
    { 
        // Autocompletar nombre si tiene al menos 1 carácter
        if (strlen($this->nuevoNombre) >= 1) {
            $results = Product::where('name', 'like', "%{$this->nuevoNombre}%")
                                ->limit(20)
                                ->get();
            $this->resultadosNombre = $results->pluck('name', 'id')->toArray();
        } else {
            $this->resultadosNombre = [];
        }
    }

    public function updatedNuevoCantidad() { $this->calcularImporteLinea(); }
    public function updatedNuevoPrecio() { $this->calcularImporteLinea(); }
    public function updatedNuevoDescuento() { $this->calcularImporteLinea(); }
    
    public function anotarLinea()
    {
        // Validación estricta: debe haber un producto cargado
        if (!$this->nuevoProducto || !isset($this->nuevoProducto->id)) {
            Notification::make()
                ->title('Artículo no encontrado')
                ->body('Busca y selecciona un artículo válido antes de añadirlo')
                ->warning()
                ->send();
            return;
        }
        
        // Si no hay ticket (después de grabar uno), crear nuevo ticket para este TPV
        if (!$this->ticket) {
            $this->cargarTpv($this->tpvActivo);
        }
        
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
        $this->nuevoProducto = null;
        $this->nuevoCantidad = 1;
        $this->nuevoPrecio = 0;
        $this->nuevoDescuento = 0;
        $this->nuevoImporte = 0;
    }
    
    /**
     * Cerrar el ticket actual y preparar uno nuevo
     */
    public function grabarTicket()
    {
        // Validar que hay líneas
        if (empty($this->lineas)) {
            Notification::make()
                ->title('No se puede grabar un ticket vacío')
                ->warning()
                ->send();
            return;
        }
        
        // IMPORTANTE: Guardar el cliente seleccionado antes de cerrar el ticket
        if (!empty($this->nuevoClienteNombre)) {
            $this->ticket->tercero_id = $this->nuevoClienteNombre;
        }
        
        // IMPORTANTE: Asegurar que todas las líneas están guardadas en la base de datos
        // Borrar todas las líneas existentes y recrearlas (para evitar inconsistencias)
        $this->ticket->items()->delete();
        
        foreach ($this->lineas as $linea) {
            $this->ticket->items()->create([
                'product_id' => $linea['product_id'],
                'quantity' => $linea['cantidad'],
                'unit_price' => $linea['precio'],
                'tax_rate' => 21, // TODO: Obtener del producto
                'subtotal' => $linea['importe'] / 1.21,
                'tax_amount' => $linea['importe'] - ($linea['importe'] / 1.21),
                'total' => $linea['importe'],
            ]);
        }
        
        // Recalcular totales finales
        $this->ticket->recalculateTotals();
        
        // Cambiar estado del ticket
        $this->ticket->status = 'completed';
        
        // Asignar número definitivo si aún es BORRADOR
        if ($this->ticket->numero === 'BORRADOR') {
            // Generar número secuencial
            $ultimoNumero = Ticket::where('status', '!=', 'open')
                                  ->max('id') ?? 0;
            $this->ticket->numero = 'TKT-' . str_pad($ultimoNumero + 1, 6, '0', STR_PAD_LEFT);
        }
        
        // Guardar ticket
        $this->ticket->save();
        
        // Notificación de éxito
        $cantidadArticulos = count($this->lineas);
        Notification::make()
            ->title('Ticket guardado')
            ->body("Ticket {$this->ticket->numero} con {$cantidadArticulos} artículos")
            ->success()
            ->send();
        
        // Limpiar todo para la siguiente venta
        $this->lineas = [];
        $this->total = 0;
        $this->limpiarInputs();
        
        // Resetear el ticket actual a null
        // Se creará uno nuevo automáticamente cuando se añada el primer artículo
        // o cuando se cambie de TPV
        $this->ticket = null;
        
        // Enfocar código para empezar de nuevo
        $this->dispatch('focus-codigo');
    }
    
    /**
     * Cargar un ticket existente en el POS
     */
    protected function cargarTicketExistente($ticket)
    {
        $this->ticket = $ticket;
        $this->tpvActivo = $ticket->tpv_slot ?? 1;
        
        // Cargar líneas
        $this->lineas = $ticket->items()->get()->map(function($item) {
            return [
                'id' => $item->id, // Guardar ID del item para edición
                'product_id' => $item->product_id,
                'codigo' => $item->product->sku ?? '---',
                'nombre' => $item->product->name,
                'cantidad' => $item->quantity,
                'precio' => $item->unit_price,
                'descuento' => 0,
                'importe' => $item->total,
            ];
        })->toArray();
        
        $this->recalcularTotales();
        
        // Cargar fecha y número
        $this->fecha = $ticket->created_at->format('Y-m-d');
        $this->data['fecha'] = $this->fecha;
        $this->data['numero'] = $ticket->id;
        
        // Cargar cliente si existe
        if ($ticket->tercero_id && $ticket->customer) {
            $this->form->fill(['tercero_id' => $ticket->tercero_id]);
            $this->nuevoClienteNombre = $ticket->tercero_id;
            if (!isset($this->resultadosClientes[$ticket->tercero_id])) {
                $this->resultadosClientes[$ticket->tercero_id] = $ticket->customer->name;
            }
        }
        
        $this->limpiarInputs();
    }
    
    /**
     * Editar una línea existente  (sube datos a barra de edición)
     */
    public function editarLinea($index)
    {
        if (!isset($this->lineas[$index])) {
            return;
        }
        
        $linea = $this->lineas[$index];
        
        // Cargar producto
        $this->nuevoProducto = Product::find($linea['product_id']);
        $this->nuevoCodigo = $linea['codigo'];
        $this->nuevoNombre = $linea['nombre'];
        $this->nuevoCantidad = $linea['cantidad'];
        $this->nuevoPrecio = $linea['precio'];
        $this->nuevoDescuento = $linea['descuento'];
        $this->calcularImporteLinea();
        
        // Eliminar la línea del array (se volverá a añadir al confirmar)
        $this->eliminarLinea($index);
        
        // Enfocar cantidad para modificar
        $this->dispatch('focus-cantidad');
        
        Notification::make()
            ->title('Artículo cargado para edición')
            ->info()
            ->send();
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
