<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Filament\Resources\CashSessionResource;
use Filament\Resources\Pages\Page;
use Livewire\Component;
use Filament\Actions;
use Filament\Forms\Set; 
use App\Models\Tercero;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;

class CreateTicket extends Page
{
    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.ticket-resource.pages.create-ticket';

    protected ?string $heading = '';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    // Propiedades para la gestión del POS
    public $tpvActivo = 1;
    public $ticket; // Modelo Ticket actual
    
    // Sesión de Caja
    public $activeSession = null;
    public $isSessionOpen = false;
    public $isProcessing = false; // Flag para evitar duplicados en concurrencia Turbo
    public $openingFund = 0;
    
    // Cierre de Caja
    public $showClosingModal = false;
    public $realFinalCash = 0;
    public $sessionNotes = '';
    public $cashBreakdown = [
        '50000' => 0, '20000' => 0, '10000' => 0, '5000' => 0, '2000' => 0, '1000' => 0, '500' => 0,
        '200' => 0, '100' => 0, '50' => 0, '20' => 0, '10' => 0, '5' => 0, '2' => 0, '1' => 0
    ];
    
    // FECHA - Propiedad dedicada para binding
    public $fecha;

    // Modal de Impresión
    public $showPrintModal = false;
    public $printUrl = '';

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
    public $quickButtons = [];

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
        $this->loadQuickButtons();
        
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
    public function cargarTpv($slot, $limpiar = true)
    {
        $this->tpvActivo = $slot;
        
        // Intentar cargar el ticket abierto más RECIENTE para este usuario y slot
        $ticketExistente = Ticket::where('tpv_slot', $slot)
                                 ->where('user_id', auth()->id())
                                 ->where('status', 'open')
                                 ->orderBy('created_at', 'desc')
                                 ->first();
        
        if ($ticketExistente) {
            $this->ticket = $ticketExistente;
        } else {
            // Solo crear si no existe uno abierto
            $this->ticket = Ticket::create([
                'tpv_slot' => $slot,
                'user_id' => auth()->id(),
                'status' => 'open',
                'session_id' => (string) \Illuminate\Support\Str::uuid(),
                'cash_session_id' => $this->activeSession?->id,
                'numero' => 'BORRADOR',
                'created_at' => now(),
            ]);
        }
        
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
        $this->recargarLineas();
        
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

        // Limpiar inputs entrada solo si se solicita
        if ($limpiar) {
            $this->limpiarInputs();
        }
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
        foreach ($this->cashBreakdown as $cents => $cantidad) {
            $total += ((float)$cents / 100) * (int)($cantidad ?: 0);
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
            
        return redirect()->to(CashSessionResource::getUrl('index'));
    }
    
    public function cargarProductosIniciales()
    {
        // Cargar primeros 15 productos alfabéticamente para datalists iniciales
        $this->resultadosCodigo = Product::orderBy('name', 'asc')->limit(15)->pluck('sku')->toArray();
        $this->resultadosNombre = Product::orderBy('name', 'asc')->limit(15)->pluck('name', 'id')->toArray();
    }
    
    public function loadQuickButtons()
    {
        $skusRaw = Setting::get('pos_quick_skus', 'BOLSA,VARIO,GENERICO');
        $skus = array_map('trim', explode(',', $skusRaw));
        
        $this->quickButtons = Product::whereIn('sku', $skus)
            ->orderByRaw("FIELD(sku, '" . implode("','", $skus) . "')")
            ->get(['sku', 'name'])
            ->toArray();
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
     * Gestión Turbo de productos
     */
    public function updatedNuevoCodigo($value)
    {
        $this->nuevoCodigo = strtoupper(trim($value));
        if (strlen($this->nuevoCodigo) >= 1) {
            $this->resultadosCodigo = Product::where('is_salable', true)
                ->where(function($q) {
                    $q->where('sku', 'like', "%{$this->nuevoCodigo}%")
                      ->orWhere('barcode', 'like', "%{$this->nuevoCodigo}%");
                })
                ->limit(20)
                ->pluck('sku')
                ->toArray();
            
            // Si hay un match EXACTO, podemos pre-cargarlo silenciosamente sin saltar el foco aún
            // Pero mejor dejar que el usuario pulse Enter para confirmar el match.
        } else {
            $this->resultadosCodigo = Product::where('is_salable', true)->orderBy('name', 'asc')->limit(15)->pluck('sku')->toArray();
        }
        
        // Solo resetear si el código realmente ha cambiado respecto al producto cargado
        if ($this->nuevoProducto && $this->nuevoProducto->sku === $this->nuevoCodigo) {
            return;
        }
        $this->nuevoProducto = null;
    }

    public function updatedNuevoNombre($value)
    {
        $v = trim($value);
        if (strlen($v) >= 1) {
            $results = Product::where('is_salable', true)
                ->where('name', 'like', "%{$v}%")
                ->limit(20)
                ->get();
            $this->resultadosNombre = $results->pluck('name', 'id')->toArray();
        } else {
            $this->resultadosNombre = Product::where('is_salable', true)->orderBy('name', 'asc')->limit(15)->pluck('name', 'id')->toArray();
        }
        
        if ($this->nuevoProducto && $this->nuevoProducto->name === $v) {
            return;
        }
        $this->nuevoProducto = null;
    }

    protected function cargarProducto($producto)
    {
        $this->nuevoProducto = $producto;
        $this->nuevoCodigo = $producto->sku; 
        $this->nuevoNombre = $producto->name; 
        $this->nuevoPrecio = $producto->price;
        $this->nuevoCantidad = 1;
        // Solo resetear descuento si no se ha introducido uno manualmente ya
        if ($this->nuevoDescuento == 0) {
            $this->nuevoDescuento = 0;
        }
        $this->calcularImporteLinea();
        
        $this->dispatch('focus-cantidad');
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
        if ($this->isProcessing) return;
        
        $codigoTrimmed = strtoupper(trim($this->nuevoCodigo ?? ''));
        $nombreTrimmed = trim($this->nuevoNombre ?? '');

        // 1. Si NO tenemos producto identificado aún, buscamos (Primer ENTER)
        if (!$this->nuevoProducto) {
            if (empty($codigoTrimmed) && empty($nombreTrimmed)) return;

            $producto = null;
            if (!empty($codigoTrimmed)) {
                $producto = Product::where('is_salable', true)
                    ->where(function($q) use ($codigoTrimmed) {
                        $q->where('sku', $codigoTrimmed)->orWhere('barcode', $codigoTrimmed);
                    })
                    ->first();
            }
            if (!$producto && !empty($nombreTrimmed)) {
                $producto = Product::where('is_salable', true)->where('name', $nombreTrimmed)->first();
                if (!$producto) {
                    $producto = Product::where('is_salable', true)->where('name', 'like', "%{$nombreTrimmed}%")->first();
                }
            }

            if ($producto) {
                $this->cargarProducto($producto); // Fija y salta a cantidad
                return;
            }

            Notification::make()->title('Producto no encontrado')->warning()->send();
            return;
        }

        // 2. Si YA tenemos producto identificado, anotamos la línea (Segundo ENTER)
        $this->isProcessing = true;
        try {
            $this->procesarLineaProducto();
            // Forzar refresco de la propiedad para Livewire
            $this->lineas = array_values($this->lineas);
        } finally {
            $this->isProcessing = false;
        }
    }

// Método auxiliar para procesar la línea cuando ya tenemos el producto
    protected function recargarLineas()
    {
        // Forzar carga fresca de la relación para evitar caché de Eloquent en memoria
        $this->lineas = $this->ticket->items()->with('product')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'codigo' => $item->product?->sku ?? '---',
                'nombre' => $item->product?->name ?? '---',
                'cantidad' => (float)$item->quantity,
                'precio' => (float)$item->unit_price,
                'descuento' => (float)($item->discount_percentage ?? 0),
                'importe' => (float)$item->total,
            ];
        })->toArray();
    }

    protected function procesarLineaProducto()
    {
        // Verificación final de seguridad
        if (!$this->nuevoProducto || !isset($this->nuevoProducto->id)) {
            return;
        }
        
        // Si no hay ticket, asegurar carga
        if (!$this->ticket) {
            $this->cargarTpv($this->tpvActivo, false);
        }
        
        $linea = [
            'product_id' => $this->nuevoProducto->id,
            'cantidad' => $this->nuevoCantidad,
            'precio' => $this->nuevoPrecio,
            'descuento' => (float)$this->nuevoDescuento,
            'importe' => $this->nuevoImporte,
        ];
        
        // 1. Persistir en BDD
        $this->persistirLinea($linea);

        // 2. UNA SOLA LLAMADA A TOTALES (Persiste y refresca en un paso)
        if ($this->ticket) {
            $this->ticket->recalculateTotals();
        }
        
        // 3. RECARGAR TODO DESDE BDD (Crucial para sincronización visual)
        $this->recargarLineas();
        
        // 4. Totales e Inputs
        $this->recalcularTotales(false); // false = no volver ya a llamar a ticket->recalculateTotals
        $this->limpiarInputs();
        
        // 5. Forzar que Livewire "vea" que el array ha cambiado de verdad
        $this->lineas = array_values($this->lineas);
    }
    
    protected function persistirLinea($linea)
    {
        // Crear TicketItem
        try {
            // Asegurar que el ticket está vinculado a la sesión activa si no lo está
            if (!$this->ticket->cash_session_id && $this->activeSession) {
                $this->ticket->cash_session_id = $this->activeSession->id;
                // No guardamos aún, lo hará recalculateTotals o al final
            }

            $taxRate = (float) ($linea['tax_rate'] ?? ($this->nuevoProducto?->tax_rate ?? 21));
            $divisor = 1 + ($taxRate / 100);

            $this->ticket->items()->create([
                'product_id' => $linea['product_id'],
                'quantity' => $linea['cantidad'],
                'unit_price' => $linea['precio'],
                'discount_percentage' => (float)($linea['descuento'] ?? 0),
                'tax_rate' => $taxRate,
                'subtotal' => round($linea['importe'] / $divisor, 4),
                'tax_amount' => round($linea['importe'] - ($linea['importe'] / $divisor), 4),
                'total' => $linea['importe'],
            ]);
            // Llamamos una sola vez al final del proceso principal
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('POS Error Save Item: '.$e->getMessage());
            Notification::make()->title('Error guardando línea: ' . $e->getMessage())->danger()->send();
        }
    }
    
    public function eliminarLinea($id)
    {
        if ($id <= 0) return;

        if ($this->ticket) {
            $this->ticket->items()->where('id', $id)->delete();
            $this->ticket->unsetRelation('items'); // Limpiar relación cargada
            $this->ticket->recalculateTotals();
            $this->ticket->refresh();
        }
        
        $this->recargarLineas();
        $this->recalcularTotales();
        
        Notification::make()
            ->title('Línea eliminada')
            ->success()
            ->send();
    }
    /**
     * Añadir producto por SKU rápido (para Bolsa, Varios, etc)
     */
    public function quickAdd($sku)
    {
        $producto = Product::where('sku', $sku)->orWhere('barcode', $sku)->first();
        if ($producto) {
            $this->cargarProducto($producto);
            // Autoconfirmar para que se añada directamente al ticket si es favorito
            $this->procesarLineaProducto();
            
            Notification::make()
                ->title('Añadido: ' . ($producto->name))
                ->success()
                ->duration(1000)
                ->send();
        } else {
            Notification::make()->title('Producto '.$sku.' no configurado')->warning()->send();
        }
    }
    
    public function recalcularTotales($recalculateModel = true)
    {
        $oldTotal = (float)$this->total;
        
        // Sincronizar con el modelo Ticket para obtener los impuestos y descuentos aplicados reales
        if ($this->ticket) {
            if ($recalculateModel) {
                $this->ticket->descuento_porcentaje = (float)($this->descuento_general_porcentaje ?: 0);
                $this->ticket->descuento_importe = (float)($this->descuento_general_importe ?: 0);
                $this->ticket->recalculateTotals();
            }
            $this->total = (float)$this->ticket->total;
        } else {
            $this->total = collect($this->lineas)->sum('importe');
        }

        // Si el total es 0 pero hay líneas, forzar recalculo del modelo (evitar glitch de carga inicial)
        if ($this->total == 0 && count($this->lineas) > 0 && $this->ticket) {
            $this->ticket->recalculateTotals();
            $this->total = (float)$this->ticket->total;
        }

        // AUTO-EQUILIBRADO DE PAGOS (Solicitado por el usuario)
        if ($this->total > 0) {
            if ($this->payment_method === 'cash') {
                $this->pago_efectivo = $this->total;
                $this->pago_tarjeta = 0;
            } elseif ($this->payment_method === 'card') {
                $this->pago_efectivo = 0;
                $this->pago_tarjeta = $this->total;
            } else {
                // Modo MIXTO o similar: si el total cambió, recalculamos la tarjeta para cerrar el ticket
                if (abs($this->total - $oldTotal) > 0.01) {
                    $this->pago_tarjeta = max(0, round($this->total - (float)$this->pago_efectivo, 2));
                }
            }
        }
        
        $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
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
            $this->initMixedPayment();
        } else {
            $this->payment_method = 'cash';
            $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
            $this->pago_efectivo = 0;
            $this->pago_tarjeta = 0;
        }
    }

    public function updatedPaymentMethod($value)
    {
        if ($value === 'cash') {
            $this->pago_efectivo = (float)$this->total;
            $this->pago_tarjeta = 0;
        } elseif ($value === 'card') {
            $this->pago_tarjeta = (float)$this->total;
            $this->pago_efectivo = 0;
        } elseif ($value === 'mixed') {
            $this->initMixedPayment();
        }
    }

    protected function initMixedPayment()
    {
        // Sugerencia: El efectivo es lo que ya hubiera en entrega, y tarjeta el resto.
        $this->pago_efectivo = (float)$this->entrega ?: (float)$this->total;
        $this->pago_tarjeta = max(0, (float)$this->total - (float)$this->pago_efectivo);
        $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
    }
    
    public function updatedPagoEfectivo() { 
        if ($this->total > 0) {
            $val = (float)($this->pago_efectivo ?: 0);
            if ($val < $this->total) {
                $this->pago_tarjeta = round($this->total - $val, 2);
            } else {
                $this->pago_tarjeta = 0;
            }
        }
        $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
    }
    
    public function updatedPagoTarjeta() { 
        if ($this->total > 0) {
            $val = (float)($this->pago_tarjeta ?: 0);
            if ($val < $this->total) {
                $this->pago_efectivo = round($this->total - $val, 2);
            } else {
                // Si la tarjeta cubre o sobra, no tocamos el efectivo (para que el cambio se vea arriba)
            }
        }
        $this->entrega = (float)$this->pago_efectivo + (float)$this->pago_tarjeta;
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
        
        // Turbo: Pre-cargar sugerencias por defecto (alfabético y vendibles)
        $this->resultadosCodigo = Product::where('is_salable', true)->orderBy('name', 'asc')->limit(15)->pluck('sku')->toArray();
        $this->resultadosNombre = Product::where('is_salable', true)->orderBy('name', 'asc')->limit(15)->pluck('name', 'id')->toArray();
        
        // Devolver foco al campo SKU para continuar añadiendo productos
        $this->dispatch('focus-codigo');
    }

    protected function refreshQuickButtons()
    {
        $this->loadQuickButtons();
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
        // Asegurar vinculación con sesión
        if (!$this->ticket->cash_session_id && $this->activeSession) {
            $this->ticket->cash_session_id = $this->activeSession->id;
            $this->ticket->save();
        }

        // Borrar todas las líneas existentes y recrearlas (para evitar inconsistencias)
        $this->ticket->items()->delete();
        
        foreach ($this->lineas as $linea) {
            $product = Product::find($linea['product_id']);
            $taxRate = (float) ($product?->tax_rate ?? 21);
            $divisor = 1 + ($taxRate / 100);

            $this->ticket->items()->create([
                'product_id' => $linea['product_id'],
                'quantity' => $linea['cantidad'],
                'unit_price' => $linea['precio'],
                'tax_rate' => $taxRate,
                'subtotal' => round($linea['importe'] / $divisor, 4),
                'tax_amount' => round($linea['importe'] - ($linea['importe'] / $divisor), 4),
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
        $this->ticket->completed_at = now();
        
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
        
        // ASEGURAR DESGLOSE DE PAGO si no se ha especificado manualmente (PAGO ÚNICO)
        $pEf = (float)($this->pago_efectivo ?: 0);
        $pTa = (float)($this->pago_tarjeta ?: 0);
        
        if ($this->payment_method === 'cash' && $pEf == 0) {
            $pEf = (float)$this->total;
        } elseif ($this->payment_method === 'card' && $pTa == 0) {
            $pTa = (float)$this->total;
        }
        
        $this->ticket->pago_efectivo = $pEf;
        $this->ticket->pago_tarjeta = $pTa;
        $this->ticket->payment_method = $this->payment_method;
        $this->ticket->amount_paid = (float)($this->entrega ?: ($pEf + $pTa));
        $this->ticket->change_given = max(0, (float)$this->ticket->amount_paid - (float)($this->total ?: 0));
        
        // Vincular a la sesión activa
        if ($this->activeSession) {
            $this->ticket->cash_session_id = $this->activeSession->id;
        }
        
        $this->ticket->save();

        // PASO 6.5: Integración Veri*Factu (Encadenamiento y Huella)
        try {
            $verifactuService = app(\App\Services\VerifactuService::class);
            $verifactuService->procesarEncadenamiento($this->ticket);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error Verifactu TPV: ' . $e->getMessage());
        }

        // PASO 7: Decrementar stock (Importante: solicitado por el usuario)
        foreach ($this->ticket->items as $item) {
            if ($item->product) {
                $item->product->decrement('stock', $item->quantity);
            }
        }
        
        // Notificación de éxito
        $cantidadArticulos = count($this->lineas);
        $savedTicket = $this->ticket;

        Notification::make()
            ->title('Ticket guardado')
            ->body("Ticket {$savedTicket->numero} con {$cantidadArticulos} artículos")
            ->success()
            ->send();
        
        // Limpiar todo para la siguiente venta
        $this->lineas = [];
        $this->total = 0;
        $this->limpiarInputs();
        
        // Resetear el ticket actual a null
        $this->ticket = null;
        
        // Enfocar código para empezar de nuevo
        $this->dispatch('focus-codigo');

        // MOSTRAR MODAL DE IMPRESIÓN (Acción total)
        $this->printUrl = route('pos.ticket', $savedTicket);
        $this->showPrintModal = true;

        return $savedTicket;
    }
    
    /**
     * Iniciar una nueva venta (limpiar TPV actual)
     */
    public function nuevaVenta()
    {
        // Si el ticket actual está vacío y es borrador, no hacemos nada especial
        // Si tiene líneas, preguntamos? (Por ahora reseteamos)
        
        // Buscar si ya existe un ticket abierto para este slot y usuario
        $ticketExistente = Ticket::where('tpv_slot', $this->tpvActivo)
                                 ->where('user_id', auth()->id())
                                 ->where('status', 'open')
                                 ->first();
        
        if ($ticketExistente) {
            // Si ya existe uno, simplemente lo limpiamos (vaciamos líneas y reseteamos totales)
            $ticketExistente->items()->delete();
            $ticketExistente->descuento_porcentaje = 0;
            $ticketExistente->descuento_importe = 0;
            $ticketExistente->pago_efectivo = 0;
            $ticketExistente->pago_tarjeta = 0;
            $ticketExistente->amount_paid = 0;
            $ticketExistente->save();
            $this->ticket = $ticketExistente;
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
        }

        // Re-asignar cliente por defecto si es necesario
        $clientePorDefectoId = \App\Models\Setting::get('pos_default_tercero_id');
        if ($clientePorDefectoId) {
            $this->ticket->tercero_id = $clientePorDefectoId;
            $this->ticket->save();
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
     * Imprimir COPIA del último ticket completado
     */
    public function imprimirTicket()
    {
        // El botón Imprimir ahora solo sirve para sacar una copia del ticket ya guardado.
        
        $ticketAPrimir = Ticket::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();

        if (!$ticketAPrimir) {
            Notification::make()
                ->title('TPV Vacío')
                ->body('No hay ningún ticket reciente para imprimir copia.')
                ->warning()
                ->send();
            return;
        }

        // Notificar al usuario si hay un ticket en curso que no ha sido guardado
        if (count($this->lineas) > 0) {
            Notification::make()
                ->title('Imprimiendo COPIA')
                ->body('Atenci&oacute;n: El ticket actual no se ha guardado todavía. Est&aacute;s imprimiendo una copia del TICKET ANTERIOR (' . $ticketAPrimir->numero . ').')
                ->info()
                ->send();
        }

        $this->printUrl = route('pos.ticket', $ticketAPrimir) . '?t=' . time();
        $this->showPrintModal = true;
    }

    /**
     * Imprimir TICKET REGALO del último ticket completado
     */
    public function imprimirTicketRegalo()
    {
        $ticketAPrimir = Ticket::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();

        if (!$ticketAPrimir) {
            Notification::make()
                ->title('TPV Vacío')
                ->body('No hay ningún ticket reciente para imprimir ticket regalo.')
                ->warning()
                ->send();
            return;
        }

        // Notificar al usuario si hay un ticket en curso que no ha sido guardado
        if (count($this->lineas) > 0) {
            Notification::make()
                ->title('Imprimiendo COPIA REGALO')
                ->body('Atenci&oacute;n: El ticket actual no se ha guardado todavía. Est&aacute;s imprimiendo una copia de REGALO del TICKET ANTERIOR (' . $ticketAPrimir->numero . ').')
                ->info()
                ->send();
        }

        $this->printUrl = route('pos.ticket', $ticketAPrimir) . '?regalo=1&t=' . time();
        $this->showPrintModal = true;
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
        
        // Eliminar la línea de la base de datos (se volverá a añadir al confirmar tras editar)
        if (isset($linea['id'])) {
            $this->eliminarLinea($linea['id']);
        }
        
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
