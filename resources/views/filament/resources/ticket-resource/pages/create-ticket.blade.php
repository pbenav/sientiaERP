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
    </style>
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
                    <input type="text" readonly
                           class="w-full h-9 bg-gray-50 border border-gray-300 rounded px-2 text-sm text-gray-600" 
                           placeholder="-" />
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
                           wire:model.live.debounce.150ms="nuevoCodigo" 
                           wire:blur="buscarProducto(true)" 
                           wire:keydown.enter="buscarProducto(true); $nextTick(() => document.getElementById('pos-descripcion').focus());"
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
                           wire:model.live.debounce.150ms="nuevoNombre" 
                           wire:blur="buscarProducto(true)" 
                           wire:keydown.enter="document.getElementById('pos-cantidad').focus()"
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
                           x-on:keydown.enter.prevent="document.getElementById('btn-anadir-producto').click()"
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
            <div class="flex gap-4 h-52 shrink-0 bg-gray-100 p-2 rounded-lg border border-gray-300">
                
                {{-- Panel Botones (Izquierda) - Grid adaptativo --}}
                <div class="grid grid-cols-3 sm:grid-cols-3 gap-2 w-full md:w-[420px]">
                     @foreach([
                        ['Grabar', 'heroicon-o-check', 'from-green-50 to-green-100 border-green-400 text-green-700 hover:from-green-100 hover:to-green-200 hover:border-green-500 hover:text-green-800 hover:shadow-xl active:from-green-600 active:to-green-700 active:border-green-800 active:text-white'],
                        ['Imprimir', 'heroicon-o-printer', 'from-white to-gray-100 border-gray-400 text-gray-700 hover:from-gray-50 hover:to-gray-200'],
                        ['Regalo', 'heroicon-o-gift', 'from-white to-gray-100 border-gray-400 text-purple-700 hover:from-gray-50 hover:to-gray-200'],
                        ['Cajón', 'heroicon-o-inbox', 'from-amber-100 to-amber-200 border-amber-400 text-amber-900 hover:from-amber-200 hover:to-amber-300'],
                        ['Nueva', 'heroicon-o-plus', 'from-amber-100 to-amber-200 border-amber-400 text-amber-900 hover:from-amber-200 hover:to-amber-300 font-black'],
                        ['Salir', 'heroicon-o-arrow-right-on-rectangle', 'from-amber-100 to-amber-200 border-amber-400 text-amber-900 hover:from-amber-200 hover:to-amber-300'],
                     ] as $i => $btn)
                     <button 
                        @if($btn[0] === 'Grabar') 
                            wire:click="grabarTicket" 
                            wire:loading.attr="disabled"
                            onclick="this.style.background='linear-gradient(to bottom, #059669, #047857)'; this.style.color='white'; this.style.transform='scale(0.9)'; setTimeout(() => { this.style.background=''; this.style.color=''; this.style.transform=''; }, 300);"
                        @endif
                        type="button"
                        class="flex flex-col items-center justify-center rounded border-2 shadow-md hover:shadow-lg transition-all duration-150 bg-gradient-to-b {{ $btn[2] }} {{ $i >= 3 ? 'ring-1 ring-amber-300' : '' }} min-h-[70px] md:min-h-[90px] {{ $btn[0] === 'Grabar' ? 'ring-2 ring-green-300' : 'active:shadow-sm active:scale-95' }}">
                        <x-dynamic-component :component="$btn[1]" class="w-8 h-8 md:w-10 md:h-10 mb-1 {{ $btn[0] === 'Grabar' ? 'transition-transform hover:scale-110' : '' }}"/>
                        <span class="font-bold text-[10px] md:text-xs leading-none text-center uppercase">{{ $btn[0] }}</span>
                        @if($btn[0] === 'Grabar')
                            <span wire:loading wire:target="grabarTicket" class="absolute inset-0 flex items-center justify-center bg-green-700 bg-opacity-90 rounded text-white font-bold text-xs">
                                GUARDANDO...
                            </span>
                        @endif
                     </button>
                     @endforeach
                </div>

                {{-- Panel Datos y Totales (Derecha) --}}
                <div class="flex-1 flex flex-col justify-between pl-4 overflow-hidden">
                    
                    {{-- Línea 1: DTO y TOTAL Gigante --}}
                    <div class="flex items-center justify-between border-b border-gray-300 pb-2 flex-1 relative">
                        <div class="text-1xl font-bold text-gray-600 self-start mt-2 absolute left-0 top-0">DTO: <span class="text-black">0 %</span></div>
                        <div class="flex items-center justify-end w-full h-full pt-6">
                            <span class="text-3xl font-bold text-gray-600 mr-6 pb-2" style="margin-right: 6px;">TOTAL:</span>
                            <span class="text-3xl font-black text-black leading-none tracking-tighter" >{{ number_format($total, 2) }}</span>
                            <span class="text-3xl font-bold text-gray-600 ml-2 pb-2" style="margin-left: 4px;">€</span>
                        </div>
                    </div>

                    {{-- Línea 2: Vendedor --}}
                    <div class="flex items-center py-2 shrink-0">
                        <span class="w-24 font-bold text-gray-700 text-lg">Vendedor:</span>
                         <div class="flex-1">
                             <select class="w-full h-10 border-gray-300 rounded text-base bg-white shadow-sm">
                                 <option>{{ auth()->user()->name }}</option>
                             </select>
                         </div>
                    </div>

                    {{-- Línea 3: Pagos y Dividir --}}
                    <div class="flex items-center space-x-3 shrink-0 pb-1">
                        <div class="flex items-center">
                            <span class="w-24 font-bold text-gray-700 text-lg">Forma Pago:</span>
                            <select wire:model="payment_method" class="h-12 border-gray-300 rounded text-base bg-white w-48 font-bold shadow-sm">
                                <option value="cash">CONTADO</option>
                                <option value="card">TARJETA</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center ml-4">
                            <span class="font-bold text-gray-700 mx-2 text-lg">Entrega:</span>
                            <input type="number" wire:model.live="entrega" class="h-12 w-32 text-right font-bold text-2xl border-gray-300 rounded focus:ring-amber-500 focus:border-amber-500 shadow-sm" />
                        </div>
                        
                        <div class="flex items-center ml-4">
                            <span class="font-bold text-gray-700 mx-2 text-lg">Vuelta:</span>
                            <input type="number" value="{{ number_format(max(0, $entrega - $total), 2) }}" readonly class="h-12 w-32 text-right font-bold text-3xl text-red-600 border-gray-300 rounded bg-gray-50 shadow-sm" />
                        </div>

                        <button class="flex flex-col items-center justify-center bg-gradient-to-b from-yellow-100 to-yellow-300 border border-yellow-400 rounded px-4 h-12 ml-auto hover:from-yellow-200 hover:to-yellow-400 transition shadow-sm" title="Dividir Pago">
                             <x-heroicon-o-banknotes class="w-6 h-6 text-yellow-800"/>
                             <span class="text-[10px] font-bold leading-none text-yellow-900 text-center mt-1">Dividir<br>Pago</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Barra Inferior de Atajos (Bottom Strip) --}}
            <div class="bg-gray-200 border-t border-gray-300 p-1 flex items-center justify-between text-[10px] text-gray-600 overflow-x-auto shrink-0 font-mono">
                @foreach([
                    'Ctrl+G' => 'Grabar',
                    'Ctrl+I' => 'Imprimir',
                    'Ctrl+A' => 'Abrir Cajón',
                    'Ctrl+N' => 'Nueva',
                    'Ctrl+S' => 'Salir',
                    'Ctrl+R' => 'Redondeo',
                    'Ctrl+T' => 'Talla',
                    'Ctrl+C' => 'Cliente',
                    'Ctrl+D' => 'Descuento',
                    'Ctrl+F' => 'F. Pago',
                    'Ctrl+V' => 'Vendedor',
                    'Ctrl+E' => 'Entrega'
                ] as $key => $label)
                <div class="flex flex-col items-center px-3 border-r border-gray-300 last:border-0 min-w-max">
                    <span class="font-bold text-black">{{ $key }}</span>
                    <span>{{ $label }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
