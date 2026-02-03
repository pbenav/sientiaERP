<div>
<x-filament-panels::page>
    @push('styles')
    <style>
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
        .pos-input:focus {
            background-color: #FEF3C7 !important; /* Amber-100 */
            outline: 2px solid #F59E0B; /* Amber-500 */
            border-color: #F59E0B;
        }
        /* Clase para auto-seleccionar al enfocar */
        .select-all { }
    </style>
    <script>
        document.addEventListener('focusin', (e) => {
            if (e.target.classList.contains('select-all') || e.target.classList.contains('pos-input')) {
                e.target.select();
            }
        });
    </script>
    @endpush

    <div x-data="{
        handleEnter(e) {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'submit') {
                const inputs = Array.from(document.querySelectorAll('input.pos-input,  button.pos-action'));
                const index = inputs.indexOf(e.target);
                if (index > -1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                    inputs[index + 1].select?.();
                    e.preventDefault();
                }
            }
        }
    }" 
    class="flex flex-col bg-white border border-gray-200 shadow-sm font-sans text-sm text-gray-900 min-h-screen md:h-[85vh] overflow-hidden md:rounded-lg">
        
        {{-- Header Compacto --}}
        <div class="bg-white border-b border-gray-200 px-3 md:px-4 py-2 shrink-0 shadow-sm">
            {{-- Fila 1: Datos del ticket y cliente --}}
            <div class="flex items-center gap-2 md:gap-4 mb-2">
                {{-- Número --}}
                <div class="w-24 md:w-32">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Número</label>
                    <input type="text" value="{{ $this->data['numero'] ?? 'AUTO' }}" readonly
                           class="w-full h-9 bg-gray-50 border border-gray-300 rounded px-2 text-sm font-mono font-medium text-gray-700" />
                </div>
                
                {{-- Fecha --}}
                <div class="w-32 md:w-40">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Fecha</label>
                    <input type="date" 
                           wire:model="fecha"
                           id="pos-fecha"
                           class="w-full h-9 border-gray-300 rounded px-2 text-sm font-medium focus:ring-primary-500 focus:border-primary-500" />
                </div>

                {{-- Cliente --}}
                <div class="flex-1">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Cliente</label>
                    <div class="relative">
                       <select wire:model.live="nuevoClienteNombre" 
                               id="pos-cliente"
                              class="w-full h-9 border-gray-300 rounded px-2 pl-8 text-sm font-bold focus:ring-primary-500 focus:border-primary-500 appearance-none">
                           <option value="">Selecciona un cliente...</option>
                           @foreach($resultadosClientes as $id => $nombre)
                               <option value="{{ $id }}">{{ $nombre }}</option>
                           @endforeach
                       </select>
                       <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-2 top-2.5 pointer-events-none" />
                       <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 absolute right-2 top-2.5 pointer-events-none" />
                    </div>
                </div>
                
                {{-- Teléfono oculto en móvil --}}
                <div class="hidden lg:block w-40">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Teléfono</label>
                    <input type="text" 
                           value="{{ $this->clienteTelefono }}"
                           readonly
                           class="w-full h-9 bg-gray-50 border border-gray-300 rounded px-2 text-sm text-gray-600" />
                </div>
            </div>
            
            {{-- Fila 2: TPV Buttons - SIEMPRE HORIZONTAL --}}
            <div class="flex gap-1">
                @foreach(range(1,4) as $tpv)
                    <button wire:click="cambiarTpv({{ $tpv }})" 
                            type="button"
                            class="flex-1 px-2 md:px-3 py-1.5 rounded text-xs font-bold transition-all duration-200 shadow-md active:scale-95
                                   {{ $tpvActivo === $tpv 
                                      ? 'bg-primary-600 text-white shadow-lg ring-2 ring-primary-300' 
                                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300 hover:shadow-lg' }}"
                            wire:loading.class="opacity-50 cursor-wait"
                            wire:target="cambiarTpv">
                        TPV {{ $tpv }}
                    </button>
                @endforeach
                <button type="button" 
                        class="hidden md:flex px-3 py-1.5 bg-gray-100 border border-gray-300 hover:bg-gray-200 rounded text-xs items-center justify-center text-gray-700 font-bold shadow-sm transition active:scale-95">
                    <x-heroicon-o-ticket class="w-3 h-3 mr-1 text-primary-500"/> VALE
                </button>
            </div>
        </div>

        {{-- Área de Trabajo --}}
        <div class="flex-1 flex flex-col p-4 space-y-4 overflow-hidden bg-gray-50/50">
            
            {{-- Fila Única de Entrada --}}
            <div class="flex items-center space-x-2 bg-white p-3 rounded-lg border border-gray-200 shadow-sm shrink-0"
                 x-data="{ focusNext(nextId) { setTimeout(() => document.getElementById(nextId)?.focus(), 100); } }"
                 @focus-cantidad.window="focusNext('pos-cantidad')"
                 @focus-precio.window="focusNext('pos-precio')"
                 @focus-descuento.window="focusNext('pos-descuento')"
                 @focus-codigo.window="focusNext('pos-codigo')">
                <div class="w-32">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Código</label>
                    <input type="text" 
                           wire:model.live="nuevoCodigo" 
                           wire:blur="buscarProducto(true)" 
                           wire:keydown.enter.prevent="buscarProducto(true)"
                           list="codigos-list"
                           id="pos-codigo"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 font-mono text-sm focus:ring-primary-500 focus:border-primary-500 uppercase" 
                           placeholder="SKU" 
                           autofocus />
                    <datalist id="codigos-list">
                        @foreach($resultadosCodigo as $id => $sku)
                            <option value="{{ $sku }}">{{ $sku }}</option>
                        @endforeach
                    </datalist>
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Descripción</label>
                    <input type="text" 
                           wire:model.live="nuevoNombre" 
                           wire:blur="buscarProducto(true)" 
                           wire:keydown.enter.prevent="buscarProducto(true)"
                           list="productos-list"
                           id="pos-descripcion"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Escribe para buscar..." />
                    <datalist id="productos-list">
                        @foreach($resultadosNombre as $id => $nombre)
                            <option value="{{ $nombre }}">{{ $nombre }}</option>
                        @endforeach
                    </datalist>
                </div>
                
                <div class="w-20">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Cant</label>
                    <input type="number" 
                           wire:model.live="nuevoCantidad"
                           x-on:keydown.enter.prevent="document.getElementById('pos-precio').focus()"
                           id="pos-cantidad"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right font-bold text-gray-800 focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-24">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Precio</label>
                    <input type="number" 
                           wire:model.live="nuevoPrecio"
                           x-on:keydown.enter.prevent="document.getElementById('pos-descuento').focus()"
                           step="0.01"
                           id="pos-precio"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-16">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Dto %</label>
                    <input type="number" 
                           wire:model.live="nuevoDescuento"
                           x-on:keydown.enter.prevent="document.getElementById('btn-anadir-producto').focus()"
                           step="0.01"
                           id="pos-descuento"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-32 bg-gray-50 rounded p-1 flex flex-col items-end justify-center border border-gray-200 h-10 px-6 mt-4" style="margin-top: 20px;"> 
                    <span class="font-bold text-lg leading-none text-primary-600">{{ number_format($nuevoImporte, 2) }}</span>
                </div>
                
                <button wire:click="anotarLinea" 
                        wire:keydown.enter="anotarLinea"
                        id="btn-anadir-producto"
                        tabindex="0"
                        class="pos-action mt-4 h-9 w-12 bg-primary-600 hover:bg-primary-500 text-white rounded shadow-sm flex items-center justify-center transition focus:ring-2 focus:ring-offset-1 focus:ring-primary-600" style="margin-top: 20px;">
                    <x-heroicon-m-plus class="w-5 h-5"/>
                </button>
            </div>

            {{-- Grid --}}
            <div class="flex-1 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-200 text-gray-700 text-xs uppercase sticky top-0">
                            <tr>
                                <th class="px-2 py-2 text-right w-12">#</th>
                                <th class="px-2 py-2 text-left w-20">Código</th>
                                <th class="px-2 py-2 text-left">Descripción</th>
                                <th class="px-2 py-2 text-right w-16">Cant.</th>
                                <th class="px-2 py-2 text-right w-20">Precio</th>
                                <th class="px-2 py-2 text-right w-16">Dto%</th>
                                <th class="px-2 py-2 text-right w-24">Importe</th>
                                <th class="px-2 py-2 text-center w-20">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($lineas as $idx => $linea)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-2 py-1 text-right text-gray-600 font-medium">{{ $idx + 1 }}</td>
                                <td class="px-2 py-1 font-mono text-xs">{{ $linea['codigo'] }}</td>
                                <td class="px-2 py-1 font-medium">{{ $linea['nombre'] }}</td>
                                <td class="px-2 py-1 text-right font-bold">{{ $linea['cantidad'] }}</td>
                                <td class="px-2 py-1 text-right">{{ number_format($linea['precio'], 2) }}</td>
                                <td class="px-2 py-1 text-right text-gray-600">{{ $linea['descuento'] }}</td>
                                <td class="px-2 py-1 text-right font-bold">{{ number_format($linea['importe'], 2) }} €</td>
                                <td class="px-2 py-1 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button wire:click="editarLinea({{ $idx }})" 
                                                class="text-amber-600 hover:text-amber-800 p-1 rounded hover:bg-amber-50"
                                                title="Editar">
                                            <x-heroicon-o-pencil class="w-4 h-4"/>
                                        </button>
                                        <button wire:click="eliminarLinea({{ $idx }})" 
                                                class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                                                title="Eliminar">
                                            <x-heroicon-o-trash class="w-4 h-4"/>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-2 py-8 text-center text-gray-400">
                                    No hay artículos añadidos
                                </td>
                            </tr>
                            @endforelse
                            
                            {{-- Filas placeholder para mantener altura fija (10 líneas) --}}
                            @for($i = count($lineas); $i < 10; $i++)
                            <tr class="border-b">
                                <td colspan="8" class="px-2 py-2">&nbsp;</td>
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer Principal --}}
            <div class="flex flex-col md:flex-row gap-4 shrink-0 bg-gray-100 p-3 rounded-lg border border-gray-300 shadow-inner">
                
                {{-- Panel Botones (Izquierda) - 2 Columnas Compacto --}}
                <div class="grid grid-cols-2 gap-2 w-fit shrink-0 overflow-visible" style="display: grid !important; grid-template-columns: repeat(2, 1fr) !important; width: 172px !important;">
                     @foreach([
                        ['Grabar', 'heroicon-o-check', 'from-green-50 to-green-100 border-green-400 text-green-700 hover:from-green-100 hover:to-green-200 hover:border-green-500 hover:text-green-800 hover:shadow-xl active:from-green-600 active:to-green-700 active:border-green-800 active:text-white'],
                        ['Anular', 'heroicon-o-trash', 'from-red-50 to-red-100 border-red-400 text-red-700 hover:from-red-100 hover:to-red-200'],
                        ['Imprimir', 'heroicon-o-printer', 'from-white to-gray-100 border-gray-400 text-gray-700 hover:from-gray-50 hover:to-gray-200'],
                        ['Nueva', 'heroicon-o-plus', 'from-amber-100 to-amber-200 border-amber-400 text-amber-900 hover:from-amber-200 hover:to-amber-300 font-black'],
                        ['Regalo', 'heroicon-o-gift', 'from-white to-gray-100 border-gray-400 text-purple-700 hover:from-gray-50 hover:to-gray-200'],
                        ['Salir', 'heroicon-o-arrow-right-on-rectangle', 'from-amber-100 to-amber-200 border-amber-400 text-amber-900 hover:from-amber-200 hover:to-amber-300'],
                     ] as $i => $btn)
                     <button 
                        @if($btn[0] === 'Grabar') 
                            wire:click="grabarTicket" 
                            wire:loading.attr="disabled"
                        @elseif($btn[0] === 'Imprimir')
                            wire:click="imprimirTicket"
                        @elseif($btn[0] === 'Anular')
                            wire:click="anularTicket"
                            wire:confirm="¿Estás seguro de que deseas anular/eliminar este ticket?"
                        @elseif($btn[0] === 'Nueva')
                            wire:click="nuevaVenta"
                        @elseif($btn[0] === 'Salir')
                            wire:click="salirPos"
                        @endif
                        @if($btn[0] === 'Grabar')
                            onclick="this.style.background='linear-gradient(to bottom, #059669, #047857)'; this.style.color='white'; this.style.transform='scale(0.9)'; setTimeout(() => { this.style.background=''; this.style.color=''; this.style.transform=''; }, 300);"
                        @endif
                        type="button"
                        class="flex flex-col items-center justify-center rounded border-2 shadow-md hover:shadow-lg transition-all duration-150 bg-gradient-to-b {{ $btn[2] }} w-20 h-20 shrink-0 {{ $btn[0] === 'Grabar' ? 'ring-2 ring-green-300 scale-105 z-10' : 'active:shadow-sm active:scale-95' }}">
                        <x-dynamic-component :component="$btn[1]" class="w-6 h-6 mb-1 {{ $btn[0] === 'Grabar' ? 'transition-transform hover:scale-110' : '' }}"/>
                        <span class="font-bold text-[9px] leading-none text-center uppercase text-black">{{ $btn[0] }}</span>
                        @if($btn[0] === 'Grabar')
                            <span wire:loading wire:target="grabarTicket" class="absolute inset-0 flex items-center justify-center bg-green-700 bg-opacity-90 rounded text-white font-bold text-[8px]">
                                ESPERE...
                            </span>
                        @endif
                     </button>
                     @endforeach
                </div>

                {{-- Panel Datos (Central) --}}
                <div class="flex-1 flex flex-col gap-4">
                    {{-- Descuentos (Arriba) --}}
                    <div class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-300 shadow-sm h-fit">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-gray-400 uppercase">Dto Gral %</span>
                            <div class="h-10 flex items-center px-4 border border-gray-200 rounded bg-gray-50/50">
                                <span class="font-bold text-lg">{{ $descuento_general_porcentaje ?: 0 }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col flex-1">
                            <span class="text-[10px] font-black text-gray-400 uppercase">Dto Gral €</span>
                            <div class="h-10 flex items-center px-4 border border-gray-200 rounded bg-gray-50/50">
                                <span class="font-bold text-lg text-right w-full">{{ $descuento_general_importe ?: 0 }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Vendedor (Abajo) --}}
                    <div class="flex-1 space-y-3">
                         <div>
                            <span class="text-[10px] font-black text-gray-400 uppercase block mb-1">Vendedor</span>
                            <div class="h-10 px-3 bg-white border border-gray-200 rounded flex items-center text-sm font-bold text-gray-700 shadow-sm">
                                {{ auth()->user()->name }}
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] font-black text-gray-400 uppercase block mb-1">Forma de Pago</span>
                            <select wire:model.live="payment_method" class="w-full h-10 border-gray-300 rounded text-sm bg-white font-black shadow-sm text-primary-700 uppercase">
                                <option value="cash">EFECTIVO</option>
                                <option value="card">TARJETA</option>
                                <option value="mixed">PAGO DIVIDIDO</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Panel Totales y Cobro (Derecha) --}}
                <div class="w-72 md:w-96 flex flex-col gap-2">
                    {{-- Total a Pagar (Arriba) --}}
                    <div class="flex flex-col items-end w-full rounded-lg bg-white p-3 border border-gray-300 shadow-sm min-h-[70px] justify-center overflow-hidden">
                        <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest leading-none mb-1">Total a Pagar</span>
                        <div class="flex items-baseline gap-1">
                            <span class="font-black tracking-tighter leading-none text-amber-500" style="font-size: 30px !important; font-weight: 950 !important; line-height: 1 !important;">{{ number_format($total, 2) }}</span>
                            <span class="font-black text-amber-500" style="font-size: 18px !important;">€</span>
                        </div>
                    </div>

                    {{-- Entrega (Medio) --}}
                    <div class="flex flex-col bg-white p-3 rounded-lg border border-gray-300 shadow-sm">
                        <div class="flex justify-between items-center mb-1">
                             <span class="text-[11px] font-black text-gray-500 uppercase">Entrega Cliente €</span>
                             <button wire:click="dividirPago" class="text-[10px] font-bold text-primary-600 hover:text-primary-800 flex items-center gap-1">
                                <x-heroicon-o-banknotes class="w-3 h-3"/> {{ $payment_method === 'mixed' ? 'Volver a único' : 'Dividir' }}
                             </button>
                        </div>
                        
                        @if($payment_method === 'mixed')
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-bold text-green-600 uppercase mb-0.5">Efectivo</span>
                                    <input type="number" wire:model.live="pago_efectivo" class="pos-input h-10 w-full text-right font-black text-lg border-green-300 rounded bg-green-50 focus:ring-green-500" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-bold text-blue-600 uppercase mb-0.5">Tarjeta</span>
                                    <input type="number" wire:model.live="pago_tarjeta" class="pos-input h-10 w-full text-right font-black text-lg border-blue-300 rounded bg-blue-50 focus:ring-blue-500" />
                                </div>
                            </div>
                        @else
                            <input type="number" wire:model.live="entrega" id="pos-entrega" class="pos-input h-10 w-full text-right font-black text-2xl border-gray-200 rounded bg-gray-50/50 shadow-inner focus:ring-primary-500" />
                        @endif
                    </div>

                    {{-- Cambio (Final) --}}
                    <div class="flex flex-col items-end bg-gray-100 px-4 py-2 rounded-lg border border-gray-200">
                        <span class="text-[10px] font-black text-gray-400 uppercase text-right leading-none mb-1">Cambio a devolver</span>
                        <div class="flex items-baseline gap-1">
                            <span class="font-black text-3xl text-gray-900 leading-none">{{ number_format(max(0, (float)$entrega - (float)$total), 2) }}</span>
                            <span class="text-lg font-black text-gray-700">€</span>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
</div>
