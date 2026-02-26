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
    
    // Sesión de Caja
    public $activeSession = null;
    public $isSessionOpen = false;
    public $openingFund = 0;
    
    // Cierre de Caja
    public $showClosingModal = false;
    public $realFinalCash = 0;
    public $sessionNotes = '';
    public $cashBreakdown = [
        '500' => 0, '200' => 0, '100' => 0, '50' => 0, '20' => 0, '10' => 0, '5' => 0,
        '2' => 0, '1' => 0, '0.50' => 0, '0.20' => 0, '0.10' => 0, '0.05' => 0, '0.02' => 0, '0.01' => 0
    ];
    
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
    public $descuento_general_porcentaje = 0;
    public $descuento_general_importe = 0;
    public $entrega = 0;
    public $pago_efectivo = 0;
    public $pago_tarjeta = 0;
    public $payment_method = 'cash'; // Método de pago (cash, card, mixed)
    public $data = []; // Datos del formulario (necesario para Page)

    // Computed property para el teléfono del cliente
    public function getClienteTelefonoProperty()
    {
        if ($this->ticket && $this->ticket->tercero_id) {
            $tercero = Tercero::find($this->ticket->tercero_id);
            return $tercero?->telefono ?? '-';
        }
        return '-';
    }

    public function mount(): void
    {
        // No llamamos parent::mount() porque gestionamos el registro manualmente
        $this->form->fill();
        
        $this->resultadosClientes = Tercero::clientes()
                                    ->activos()
                                    ->orderBy('nombre_comercial')
                                    ->limit(10)
                                    ->pluck('nombre_comercial', 'id')
                                    ->toArray();
        
        // Verificar sesión activa
        $this->activeSession = \App\Models\CashSession::where('user_id', auth()->id())
            ->where('estado', 'open')
            ->first();
        
        $this->isSessionOpen = (bool) $this->activeSession;
        
        // Si no hay sesión abierta, intentar proponer fondo de apertura del último cierre
        if (!$this->isSessionOpen) {
            $ultimaSesion = \App\Models\CashSession::where('user_id', auth()->id())
                ->where('estado', 'closed')
                ->orderBy('fecha_fin', 'desc')
                ->first();
            $this->openingFund = $ultimaSesion ? $ultimaSesion->efectivo_final_real : 0;
        }
        
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
        $this->data['numero'] = $this->ticket->numero;
        
        // IMPORTANTE: Recargar todos los clientes activos para el dropdown (no solo los 10 iniciales)
        // Esto permite cambiar el cliente en cualquier momento
        $this->resultadosClientes = Tercero::clientes()
                                    ->activos()
                                    ->orderBy('nombre_comercial')
                                    ->limit(50)  // Aumentado a 50 para tener más opciones
                                    ->pluck('nombre_comercial', 'id')
                                    ->toArray();
        
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
        
        // Cargar descuentos y pagos del ticket
        $this->descuento_general_porcentaje = $this->ticket->descuento_porcentaje;
        $this->descuento_general_importe = $this->ticket->descuento_importe;
        $this->pago_efectivo = $this->ticket->pago_efectivo;
        $this->pago_tarjeta = $this->ticket->pago_tarjeta;
        $this->payment_method = $this->ticket->payment_method ?? 'cash';
        $this->entrega = $this->ticket->amount_paid;

        // Limpiar inputs entrada
        $this->limpiarInputs();
    }
    
    /**
     * Abrir una nueva sesión de caja
     */
    public function openSession()
    {
        $this->activeSession = \App\Models\CashSession::create([
            'user_id' => auth()->id(),
            'fecha_inicio' => now(),
            'estado' => 'open',
            'fondo_apertura' => $this->openingFund,
        ]);
        
        $this->isSessionOpen = true;
        
        Notification::make()
            ->title('Sesión iniciada')
            ->body("Fondo de apertura: " . number_format($this->openingFund, 2) . " €")
            ->success()
            ->send();
            
        $this->dispatch('close-modal', id: 'modal-apertura');
    }
    
    /**
     * Iniciar proceso de cierre de caja
     */
    public function openClosingModal()
    {
        $this->calcularTotalesSesion();
        $this->showClosingModal = true;
    }
    
    protected function calcularTotalesSesion()
    {
        if (!$this->activeSession) return;
        
        $tickets = $this->activeSession->tickets()->where('status', 'completed')->get();
        
        $this->activeSession->total_tickets_efectivo = $tickets->sum('pago_efectivo');
        $this->activeSession->total_tickets_tarjeta = $tickets->sum('pago_tarjeta');
        $this->activeSession->save();
    }
    
    /**
     * Calcular total de efectivo real basado en el desglose
     */
    public function updatedCashBreakdown()
    {
        $total = 0;
        foreach ($this->cashBreakdown as $denominacion => $cantidad) {
            $total += (float)$denominacion * (int)($cantidad ?: 0);
        }
        $this->realFinalCash = round($total, 2);
    }
    
    /**
     * Confirmar y cerrar la sesión de caja
     */
    public function confirmSessionClosure()
    {
        if (!$this->activeSession) return;
        
        $this->calcularTotalesSesion();
        
        $totalTeorico = $this->activeSession->fondo_apertura + $this->activeSession->total_tickets_efectivo;
        $desfase = $this->realFinalCash - $totalTeorico;
        
        $this->activeSession->update([
            'fecha_fin' => now(),
            'estado' => 'closed',
            'efectivo_final_real' => $this->realFinalCash,
            'desfase' => $desfase,
            'notas' => $this->sessionNotes,
            'desglose_efectivo' => $this->cashBreakdown,
        ]);
        
        $this->activeSession = null;
        $this->isSessionOpen = false;
        $this->showClosingModal = false;
        
        Notification::make()
            ->title('Sesión cerrada correctamente')
            ->body("Efectivo real: {$this->realFinalCash} € | Desfase: " . number_format($desfase, 2) . " €")
            ->success()
            ->persistent()
            ->send();
            
        return redirect()->to(TicketResource::getUrl('index'));
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
                
                // Notificación de feedback
                Notification::make()
                    ->title('Cliente actualizado')
                    ->body($tercero->nombre_comercial)
                    ->success()
                    ->duration(2000)
                    ->send();
                
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
    // VALIDACIÓN 1: Si ya hay producto cargado, usar ese directamente
    if ($this->nuevoProducto && isset($this->nuevoProducto->id)) {
        // Ya tenemos el producto, continuar con la lógica de añadir
        $this->procesarLineaProducto();
        return;
    }
    
    // VALIDACIÓN 2: Si NO hay producto cargado, verificar si hay algo que buscar
    // Trimear y verificar que no estén vacíos
    $codigoTrimmed = trim($this->nuevoCodigo ?? '');
    $nombreTrimmed = trim($this->nuevoNombre ?? '');
    
    // Si AMBOS campos están vacíos, no hacer nada (return silencioso)
    if (empty($codigoTrimmed) && empty($nombreTrimmed)) {
        return; // Silencioso, no molestar al usuario
    }
    
    // VALIDACIÓN 3: Intentar buscar el producto SOLO si hay datos válidos
    $producto = null;
    
    // Buscar por código si hay algo válido
    if (!empty($codigoTrimmed)) {
        $producto = Product::where('sku', $codigoTrimmed)
                           ->orWhere('barcode', $codigoTrimmed)
                           ->first();
    }
    
    // Si no se encontró por código, buscar por nombre si hay algo válido
    if (!$producto && !empty($nombreTrimmed)) {
        $producto = Product::where('name', $nombreTrimmed)
                           ->orWhere('name', 'like', "%{$nombreTrimmed}%")
                           ->first();
    }
    
    // Si se encontró, cargarlo y procesar
    if ($producto) {
        $this->cargarProducto($producto);
        $this->procesarLineaProducto();
        return;
    }
    
    // Si llegamos aquí, no se encontró el producto
    Notification::make()
        ->title('Producto no encontrado')
        ->body('No se encontró ningún producto con: ' . ($codigoTrimmed ?: $nombreTrimmed))
        ->warning()
        ->send();
}

// Método auxiliar para procesar la línea cuando ya tenemos el producto
protected function procesarLineaProducto()
{
    // Verificación final de seguridad
    if (!$this->nuevoProducto || !isset($this->nuevoProducto->id)) {
        return;
    }
    
    // Si no hay ticket, crear uno nuevo para este TPV
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
        $oldTotal = $this->total;
        $subtotalLineas = collect($this->lineas)->sum('importe');

        // El ticket model tiene la lógica de descuentos
        if ($this->ticket) {
            $this->ticket->descuento_porcentaje = $this->descuento_general_porcentaje;
            $this->ticket->descuento_importe = $this->descuento_general_importe;
            $this->ticket->recalculateTotals();
            $this->total = $this->ticket->total;
        } else {
            $this->total = $subtotalLineas;
        }

        if ($this->payment_method === 'mixed') {
            // ── PAGO DIVIDIDO ─────────────────────────────────────────────────
            // Si el total cambió, rebalancear: mantener efectivo, recalcular tarjeta
            if ((float)$this->total !== (float)$oldTotal || $this->pago_efectivo + $this->pago_tarjeta == 0) {
                $efectivo = (float)($this->pago_efectivo ?: 0);
                if ($efectivo > 0 && $efectivo < $this->total) {
                    // El efectivo es válido: ajustar tarjeta al resto
                    $this->pago_tarjeta = round($this->total - $efectivo, 2);
                } elseif ($efectivo >= $this->total && $this->total > 0) {
                    // Descuento dejó el total por debajo del efectivo introducido
                    $this->pago_efectivo = $this->total;
                    $this->pago_tarjeta  = 0;
                }
                // Si efectivo == 0: no tocar (el usuario aún no ha introducido nada)
                $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
            }
        } else {
            // ── PAGO ÚNICO (efectivo o tarjeta) ───────────────────────────────
            if ($this->entrega == 0 || $this->entrega == $oldTotal) {
                $this->entrega = $this->total;
            }
        }
    }
    
    public function updatedDescuentoGeneralPorcentaje() { $this->recalcularTotales(); }
    public function updatedDescuentoGeneralImporte() { $this->recalcularTotales(); }
    public function updatedEntrega() { 
        if ($this->payment_method === 'cash') {
            $this->pago_efectivo = $this->entrega;
            $this->pago_tarjeta = 0;
        } elseif ($this->payment_method === 'card') {
            $this->pago_tarjeta = $this->entrega;
            $this->pago_efectivo = 0;
        }
    }
    
    /**
     * Alternar entre Pago Único y Pago Dividido
     */
    public function dividirPago()
    {
        if ($this->payment_method !== 'mixed') {
            $this->payment_method = 'mixed';
            // Por defecto mitad y mitad o similar? Por ahora 0/0
            $this->pago_efectivo = 0;
            $this->pago_tarjeta = 0;
        } else {
            $this->payment_method = 'cash';
            $this->pago_efectivo = $this->entrega;
            $this->pago_tarjeta = 0;
        }
    }
    
    public function updatedPagoEfectivo() { 
        if ($this->payment_method === 'mixed') {
            $this->pago_efectivo = (float)($this->pago_efectivo ?: 0);
            
            // Auto-equilibrado: Si el efectivo es menor que el total, el resto va a tarjeta
            if ($this->total > 0 && $this->pago_efectivo < $this->total) {
                $this->pago_tarjeta = (float)$this->total - (float)$this->pago_efectivo;
            } else {
                // Si ya cubrimos el total con efectivo, la tarjeta queda en 0
                $this->pago_tarjeta = 0;
            }
            
            $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
        }
    }
    
    public function updatedPagoTarjeta() { 
        if ($this->payment_method === 'mixed') {
            $this->pago_tarjeta = (float)($this->pago_tarjeta ?: 0);
            
            // Auto-equilibrado: Si la tarjeta es menor que el total, el resto va a efectivo
            if ($this->total > 0 && $this->pago_tarjeta < $this->total) {
                $this->pago_efectivo = (float)$this->total - (float)$this->pago_tarjeta;
            } else {
                // Si la tarjeta ya cubre el total, el efectivo se queda como estaba (o 0 si se prefiere)
                // Usualmente el efectivo es lo que genera cambio, así que lo dejamos para el cálculo final
            }
            
            $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
        }
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
        
        // Devolver foco al campo SKU para continuar añadiendo productos
        $this->dispatch('focus-codigo');
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
        
        // PASO 1: Asegurar que todas las líneas están guardadas en la base de datos
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
        
        // PASO 2: Recalcular totales finales
        $this->ticket->recalculateTotals();
        
        // PASO 3: IMPORTANTE - Guardar el cliente seleccionado ANTES de cambiar el estado
        // Convertir a entero si es numérico, ya que puede venir como string del select
        if (!empty($this->nuevoClienteNombre)) {
            $terceroId = is_numeric($this->nuevoClienteNombre) 
                ? (int)$this->nuevoClienteNombre 
                : null;
            
            if ($terceroId) {
                $this->ticket->tercero_id = $terceroId;
            }
        }
        
        // PASO 4: Cambiar estado del ticket
        $this->ticket->status = 'completed';
        
        // PASO 5: Asignar número definitivo si aún es BORRADOR
        if ($this->ticket->numero === 'BORRADOR') {
            $year = now()->year;
            // Buscar el último ticket emitido en el año actual que tenga formato TKT-YYYY-XXXXXX
            $ultimoTicket = Ticket::where('numero', 'like', "TKT-{$year}-%")
                                  ->orderBy('numero', 'desc')
                                  ->first();
            
            $siguienteSecuencia = 1;
            if ($ultimoTicket) {
                // Extraer la parte numérica (los últimos 6 dígitos)
                $partes = explode('-', $ultimoTicket->numero);
                if (count($partes) === 3) {
                    $siguienteSecuencia = (int)$partes[2] + 1;
                }
            }
            
            $this->ticket->numero = 'TKT-' . $year . '-' . str_pad($siguienteSecuencia, 6, '0', STR_PAD_LEFT);
        }
        
        // PASO 6: Guardar ticket con TODOS los cambios de una vez
        $this->ticket->descuento_porcentaje = (float)($this->descuento_general_porcentaje ?: 0);
        $this->ticket->descuento_importe = (float)($this->descuento_general_importe ?: 0);
        $this->ticket->pago_efectivo = (float)($this->pago_efectivo ?: 0);
        $this->ticket->pago_tarjeta = (float)($this->pago_tarjeta ?: 0);
        $this->ticket->payment_method = $this->payment_method;
        $this->ticket->amount_paid = (float)($this->entrega ?: 0);
        $this->ticket->change_given = max(0, (float)($this->entrega ?: 0) - (float)($this->total ?: 0));
        
        // Vincular a la sesión activa
        if ($this->activeSession) {
            $this->ticket->cash_session_id = $this->activeSession->id;
        }
        
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
     * Iniciar una nueva venta (limpiar TPV actual)
     */
    public function nuevaVenta()
    {
        // Si el ticket actual está vacío y es borrador, no hacemos nada especial
        // Si tiene líneas, preguntamos? (Por ahora reseteamos)
        
        if ($this->ticket && $this->ticket->status === 'open' && $this->ticket->items()->count() === 0) {
            // Ya está vacío, solo limpiar inputs
        } else {
            // Crear un nuevo ticket para este slot
            $this->ticket = Ticket::create([
                'tpv_slot' => $this->tpvActivo,
                'user_id' => auth()->id(),
                'session_id' => (string) \Illuminate\Support\Str::uuid(),
                'numero' => 'BORRADOR',
                'status' => 'open',
                'created_at' => now(),
            ]);
            
            // Asignar cliente por defecto
            $clientePorDefectoId = \App\Models\Setting::get('pos_default_tercero_id');
            if ($clientePorDefectoId) {
                $this->ticket->tercero_id = $clientePorDefectoId;
                $this->ticket->save();
            }
        }
        
        $this->lineas = [];
        $this->total = 0;
        $this->limpiarInputs();
        
        Notification::make()
            ->title('Nueva venta iniciada')
            ->success()
            ->send();
    }
    
    /**
     * Anular/Borrar el ticket actual
     */
    public function anularTicket()
    {
        if (!$this->ticket) return;
        
        if ($this->ticket->numero === 'BORRADOR') {
            // Borrar físicamente si estaba abierto y sin confirmar
            $this->ticket->delete();
            
            Notification::make()
                ->title('Venta anulada y borrada')
                ->warning()
                ->send();
        } else {
            // Si ya tiene número (está completado o fue una edición), usar la lógica de cancelación oficial
            $this->ticket->cancel();
            
            Notification::make()
                ->title('Ticket ANULADO')
                ->body('El ticket ha sido marcado como anulado y se ha devuelto el stock.')
                ->danger()
                ->send();
        }
        
        // Iniciar nuevo
        $this->cargarTpv($this->tpvActivo);
    }
    
    /**
     * Salir del POS
     */
    public function salirPos()
    {
        return redirect()->to(TicketResource::getUrl('index'));
    }
    
    /**
     * Imprimir ticket (Placeholder)
     */
    public function imprimirTicket()
    {
        Notification::make()
            ->title('Impresión')
            ->body('Funcionalidad de impresión en desarrollo')
            ->info()
            ->send();
    }
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
        $this->data['numero'] = $ticket->numero;
        
        // Cargar cliente si existe
        if ($ticket->tercero_id && $ticket->tercero) {
            $this->form->fill(['tercero_id' => $ticket->tercero_id]);
            $this->nuevoClienteNombre = $ticket->tercero_id;
            if (!isset($this->resultadosClientes[$ticket->tercero_id])) {
                $this->resultadosClientes[$ticket->tercero_id] = $ticket->tercero->nombre_comercial;
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
