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
                background-color: #FEF3C7 !important;
                outline: 2px solid #F59E0B;
                border-color: #F59E0B;
            }
        </style>
        <script>
            document.addEventListener('focusin', (e) => {
                if (e.target.classList.contains('select-all') || e.target.classList.contains('pos-input')) {
                    e.target.select();
                }
            });

            window.addEventListener('print-ticket', (event) => {
                window.open(event.detail.url, '_blank');
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
        class="flex flex-col text-gray-500 bg-white border border-gray-200 shadow-sm font-sans text-sm w-full md:rounded-lg">
        {{-- Barra de Navegación Profesional --}}
        <div class="bg-primary-600 text-white px-4 py-2 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-computer-desktop class="w-5 h-5 text-white" />
                    <span class="font-black text-lg tracking-tight">SIENTIA <span
                            class="text-primary-200">POS</span></span>
                </div>

                {{-- Selector de Ventas Simultáneas (TPV 1-4) --}}
                <div class="flex gap-1 bg-primary-700/50 p-1 rounded-lg border border-white/10">
                    @foreach (range(1, 4) as $tpv)
                        <button wire:click="cambiarTpv({{ $tpv }})" type="button"
                            class="px-3 py-1 rounded text-[10px] font-black transition-all duration-200 uppercase
                                       {{ (int) $tpvActivo === (int) $tpv
                                           ? 'bg-white text-primary-700 shadow-inner'
                                           : 'text-primary-100 hover:bg-primary-500 hover:text-white' }}"
                            wire:loading.attr="disabled">
                            Venta {{ $tpv }}
                        </button>
                    @endforeach
                </div>

                <div
                    class="hidden lg:flex items-center gap-3 px-3 py-1 bg-primary-700/50 rounded-full border border-white/20">
                    <div class="flex items-center gap-1.5">
                        <x-heroicon-s-user class="w-3.5 h-3.5 text-primary-100" />
                        <span class="text-[10px] uppercase font-bold text-white">{{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($isSessionOpen)
                    <button wire:click="openClosingModal" type="button"
                        class="group flex items-center gap-1.5 px-4 py-1.5 bg-amber-500 hover:bg-amber-600 rounded-lg font-black text-[11px] uppercase transition shadow-lg active:scale-95 text-black">
                        <x-heroicon-s-banknotes class="w-4 h-4 group-hover:rotate-12 transition-transform" />
                        ARQUEO / CIERRE
                    </button>
                @endif

                <button wire:click="salirPos" type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary-700 hover:bg-red-600 rounded-md font-black text-[11px] uppercase transition shadow-lg active:scale-95 border border-white/20 text-white">
                    <x-heroicon-s-arrow-left-on-rectangle class="w-4 h-4" />
                    SALIR
                </button>
            </div>
        </div>

        {{-- Header Compacto --}}
        <div class="bg-white border-b border-gray-200 px-3 md:px-4 py-2 shrink-0 shadow-sm">
            <div class="flex items-center gap-2 md:gap-4">
                {{-- Número --}}
                <div class="w-16 md:w-24">
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-0.5 leading-none">Num</label>
                    <input type="text" value="{{ $this->data['numero'] ?? 'AUTO' }}" readonly
                        class="w-full h-8 bg-gray-50 border border-gray-200 rounded px-2 text-xs font-mono font-medium text-gray-700" />
                </div>

                {{-- Fecha --}}
                <div class="w-36 md:w-48">
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-0.5 leading-none">Fecha</label>
                    <input type="date" wire:model="fecha" id="pos-fecha"
                        class="w-full h-8 border-gray-200 rounded px-2 text-sm font-medium focus:ring-primary-500 focus:border-primary-500" />
                </div>

                {{-- Cliente --}}
                <div class="flex-1 min-w-[150px]">
                    <label
                        class="block text-[10px] uppercase font-bold text-gray-400 mb-0.5 leading-none">Cliente</label>
                    <select wire:model.live="nuevoClienteNombre" id="pos-cliente"
                        class="w-full h-8 border-gray-200 rounded px-2 text-sm font-bold focus:ring-primary-500 focus:border-primary-500 appearance-none py-0">
                        <option value="">Selecciona un cliente...</option>
                        @foreach ($resultadosClientes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Pago --}}
                <div class="w-40">
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-0.5 leading-none">Pago</label>
                    <select wire:model.live="payment_method"
                        class="w-full h-8 border-gray-200 rounded text-xs bg-white font-black shadow-sm text-primary-700 uppercase py-0">
                        <option value="cash">EFECTIVO</option>
                        <option value="card">TARJETA</option>
                        <option value="mixed">PAGO DIVIDIDO</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Área de Trabajo --}}
        <div class="w-full flex flex-col p-1 md:p-2 gap-1 md:gap-2 bg-gray-50/50 relative"
            wire:loading.class="opacity-75 pointer-events-none cursor-wait"
            wire:target="anotarLinea,quickAdd,eliminarLinea">

            {{-- Fila Única de Entrada --}}
            <div class="flex items-center space-x-1 bg-white p-2 rounded border border-gray-200 shadow-xs shrink-0"
                x-data="{ focusNext(nextId) { setTimeout(() => { let el = document.getElementById(nextId); if (el) { el.focus();
                                el.select(); } }, 50); } }" @focus-cantidad.window="focusNext('pos-cantidad')"
                @focus-precio.window="focusNext('pos-precio')" @focus-descuento.window="focusNext('pos-descuento')"
                @focus-codigo.window="focusNext('pos-codigo')">
                <div class="w-32" wire:key="container-codigo">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Código</label>
                    <input type="text" wire:model.live.debounce.500ms="nuevoCodigo"
                        wire:keydown.enter.prevent="anotarLinea" list="codigos-list" id="pos-codigo" autocomplete="off"
                        onfocus="this.select()"
                        class="pos-input w-full h-9 border-gray-300 rounded-none px-2 font-mono text-sm focus:ring-primary-500 focus:border-primary-500 uppercase"
                        placeholder="SKU" autofocus />
                    <datalist id="codigos-list">
                        @foreach ($resultadosCodigo as $id => $sku)
                            <option value="{{ $sku }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="flex-1 min-w-[200px]" wire:key="container-descripcion">
                    <label
                        class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Descripción</label>
                    <input type="text" wire:model.live.debounce.500ms="nuevoNombre"
                        wire:keydown.enter.prevent="anotarLinea" list="productos-list" id="pos-descripcion"
                        autocomplete="off" onfocus="this.select()"
                        class="pos-input w-full h-9 border-gray-300 rounded-none px-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Escribe para buscar..." />
                    <datalist id="productos-list">
                        @foreach ($resultadosNombre as $id => $nombre)
                            <option value="{{ $nombre }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="w-20">
                    <label
                        class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Cant</label>
                    <input type="number" wire:model.blur="nuevoCantidad"
                        x-on:keydown.enter.prevent="$wire.anotarLinea()" id="pos-cantidad" onfocus="this.select()"
                        class="pos-input w-full h-9 border-gray-300 rounded-none px-2 text-right font-bold text-gray-800 focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-24">
                    <label
                        class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Precio</label>
                    <input type="number" wire:model.blur="nuevoPrecio"
                        x-on:keydown.enter.prevent="document.getElementById('pos-descuento').focus()" step="0.01"
                        id="pos-precio" onfocus="this.select()"
                        class="pos-input w-full h-9 border-gray-300 rounded-none px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-16">
                    <label
                        class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Dto%</label>
                    <input type="number" wire:model.blur="nuevoDescuento"
                        x-on:keydown.enter.prevent="$wire.anotarLinea()"
                        x-on:keydown.tab.prevent="$wire.anotarLinea()" step="0.01" id="pos-descuento"
                        onfocus="this.select()"
                        class="pos-input w-full h-9 border-gray-300 rounded-none px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-32 bg-gray-50 rounded p-1 flex flex-col items-end justify-center border border-gray-200 h-10 px-6 mt-4"
                    style="margin-top: 20px;">
                    <span
                        class="font-bold text-lg leading-none text-primary-600">{{ number_format($nuevoImporte, 2) }}</span>
                </div>

                <button wire:click="anotarLinea" wire:keydown.enter="anotarLinea" id="btn-anadir-producto"
                    tabindex="0"
                    class="pos-action mt-3 h-8 w-10 bg-primary-600 hover:bg-primary-500 text-white rounded-none shadow-sm flex items-center justify-center transition focus:ring-2 focus:ring-offset-1 focus:ring-primary-600"
                    style="margin-top: 15px;">
                    <x-heroicon-m-plus class="w-5 h-5" />
                </button>
            </div>

            {{-- Botonera de Acceso Rápido --}}
            <div class="flex flex-wrap gap-1 px-1 mb-1">
                @forelse($quickButtons as $qb)
                    <button wire:click="quickAdd('{{ $qb['sku'] }}')" type="button"
                        class="px-3 py-1 bg-gray-100 text-gray-700 border border-gray-300 text-[9px] font-black uppercase hover:bg-primary-600 hover:text-white hover:border-primary-600 transition shadow-xs rounded-none">
                        + {{ $qb['name'] }}
                    </button>
                @empty
                    <span class="text-[9px] text-gray-400 italic px-2">Configura SKUs habituales en Ajustes para ver
                        botones rápidos aquí (ej: BOLSA, VARIO)</span>
                @endforelse
            </div>

            {{-- Grid --}}
            <div
                class="border border-gray-200 rounded-none bg-white shadow-sm flex flex-col h-[65vh] md:h-[calc(100vh-280px)] overflow-hidden shrink-0">
                <div class="overflow-y-auto w-full h-full">
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
                                <tr class="border-b hover:bg-gray-50 bg-white"
                                    wire:key="ticket-line-{{ $idx }}-{{ $linea['product_id'] }}">
                                    <td class="px-2 py-1 text-right text-gray-600 font-medium">{{ $idx + 1 }}
                                    </td>
                                    <td class="px-2 py-1 font-mono text-xs">{{ $linea['codigo'] }}</td>
                                    <td class="px-2 py-1 font-medium">{{ $linea['nombre'] }}</td>
                                    <td class="px-2 py-1 text-right font-bold">{{ $linea['cantidad'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ number_format($linea['precio'], 2) }}</td>
                                    <td class="px-2 py-1 text-right text-gray-600">{{ $linea['descuento'] }}</td>
                                    <td class="px-2 py-1 text-right font-bold">
                                        {{ number_format($linea['importe'], 2) }} €</td>
                                    <td class="px-2 py-1 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="editarLinea({{ $idx }})"
                                                class="text-amber-600 hover:text-amber-800 p-1 rounded hover:bg-amber-50"
                                                title="Editar">
                                                <x-heroicon-o-pencil class="w-4 h-4" />
                                            </button>
                                            <button wire:click="eliminarLinea({{ $idx }})"
                                                class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                                                title="Eliminar">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-2 py-2 text-center text-gray-400 text-xs italic">
                                        No hay artículos añadidos
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Filas placeholder para mantener altura fija (10 líneas) --}}
                            @for ($i = count($lineas); $i < 10; $i++)
                                <tr class="border-b">
                                    <td colspan="8" class="px-2 py-2">&nbsp;</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer Principal --}}
            {{-- Footer Principal (Dos Filas para Máximo Espacio) --}}
            <div
                class="flex flex-col gap-3 shrink-0 bg-white p-4 rounded-t-2xl border-t border-x border-gray-300 shadow-xl overflow-hidden mt-2">

                {{-- Fila 1: Botonera de Acciones --}}
                <div class="flex flex-row gap-2 items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex gap-2 items-center">
                        @foreach ([['Grabar', 'heroicon-o-check', 'background-color: #16a34a; color: white;', 'scale-105 !px-12 mx-2 font-black text-xl shadow-green-200'], ['Anular', 'heroicon-o-trash', 'background-color: #fef2f2; color: #dc2626;', 'font-bold'], ['Imprimir', 'heroicon-o-printer', 'background-color: #f3f4f6; color: #374151;', 'font-bold'], ['Regalo', 'heroicon-o-gift', 'background-color: #f3f4f6; color: #7e22ce;', 'font-bold'], ['Nueva', 'heroicon-o-plus', 'background-color: #f59e0b; color: white;', 'font-bold'], ['Salir', 'heroicon-o-arrow-right-on-rectangle', 'background-color: #f3f4f6; color: #b45309;', 'font-bold']] as $btn)
                            <button
                                @if ($btn[0] === 'Grabar') wire:click="grabarTicket" wire:loading.attr="disabled"
                                @elseif($btn[0] === 'Imprimir') wire:click="imprimirTicket"
                                @elseif($btn[0] === 'Anular') wire:click="anularTicket" wire:confirm="¿Anular?"
                                @elseif($btn[0] === 'Nueva') wire:click="nuevaVenta"
                                @elseif($btn[0] === 'Regalo') wire:click="imprimirTicketRegalo"
                                @elseif($btn[0] === 'Salir') wire:click="salirPos" @endif
                                type="button" style="{{ $btn[2] }}"
                                class="flex items-center gap-3 px-6 h-14 rounded-none border border-gray-400 shadow-sm transition-all duration-150 {{ $btn[3] }} active:scale-95 hover:brightness-95">
                                <x-dynamic-component :component="$btn[1]" class="w-6 h-6" />
                                <span class="uppercase tracking-wide font-black">{{ $btn[0] }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Vendedor --}}
                    <div class="flex flex-col items-end">
                        <span class="text-[9px] uppercase font-black text-gray-400 mb-0.5">Vendedor</span>
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-none border border-gray-200">
                            <x-heroicon-s-user class="w-3.5 h-3.5 text-gray-400" />
                            <span class="font-black text-xs text-gray-700 uppercase">{{ auth()->user()->name }}</span>
                        </div>
                    </div>
                </div>

                {{-- Fila 2: Totales y Pagos --}}
                <div class="flex flex-row gap-4 items-end">

                    {{-- Bloque Descuentos --}}
                    <div class="flex gap-2 bg-gray-50 p-3 rounded-none border border-gray-200 shadow-inner">
                        <div class="w-20">
                            <label
                                class="block text-[9px] uppercase font-black text-gray-500 mb-1 leading-none text-right">Dto
                                %</label>
                            <input onfocus="this.select()" type="number"
                                wire:model.live.debounce.500ms="descuento_general_porcentaje" step="0.01"
                                class="h-9 w-full text-right text-sm border-gray-300 rounded-none font-bold" />
                        </div>
                        <div class="w-28">
                            <label
                                class="block text-[9px] uppercase font-black text-gray-500 mb-1 leading-none text-right">Dto
                                €</label>
                            <input onfocus="this.select()" type="number"
                                wire:model.live.debounce.500ms="descuento_general_importe" step="0.01"
                                class="h-9 w-full text-right text-base border-gray-300 rounded-none font-black bg-white" />
                        </div>
                    </div>

                    {{-- Bloque Pagos --}}
                    <div class="flex-1 flex gap-3 bg-gray-50 p-3 rounded-none border border-gray-200 shadow-inner">
                        <div class="flex flex-col flex-1">
                            <span class="text-[10px] font-black text-green-700 uppercase leading-none mb-1.5">Efectivo
                                (€)</span>
                            <input onfocus="this.select()" type="number" wire:model.live="pago_efectivo"
                                @keydown.enter="$wire.grabarTicket()"
                                class="h-10 w-full text-right text-xl border-green-300 rounded-none bg-white font-black text-green-800 shadow-sm focus:ring-green-500" />
                        </div>
                        <div class="flex flex-col flex-1">
                            <span class="text-[10px] font-black text-blue-700 uppercase leading-none mb-1.5">Tarjeta
                                (€)</span>
                            <input onfocus="this.select()" type="number" wire:model.live="pago_tarjeta"
                                @keydown.enter="$wire.grabarTicket()"
                                class="h-10 w-full text-right text-xl border-blue-300 rounded-none bg-white font-black text-blue-800 shadow-sm focus:ring-blue-500" />
                        </div>
                    </div>

                    {{-- Dinámica de Cambio / Pendiente --}}
                    @php
                        $receivedTotal = (float) ($pago_efectivo ?? 0) + (float) ($pago_tarjeta ?? 0);
                        $balVal = $receivedTotal - (float) ($total ?? 0);
                        $isPendingV = $balVal < -0.01;
                    @endphp
                    <div
                        class="flex flex-col items-end p-3 px-6 rounded-none w-44 border-2 shadow-lg transition-all duration-300 {{ $isPendingV ? 'bg-red-100 border-red-500' : 'bg-green-100 border-green-500' }}">
                        <span
                            class="text-[10px] uppercase font-black {{ $isPendingV ? 'text-red-700' : 'text-green-800' }} leading-none mb-1">
                            {{ $isPendingV ? 'FALTAN' : 'CAMBIO' }}
                        </span>
                        <div class="flex items-baseline gap-1 {{ $isPendingV ? 'text-red-900' : 'text-green-900' }}">
                            <span class="font-black text-3xl leading-none">{{ number_format(abs($balVal), 2) }}</span>
                            <span class="text-xs font-black">€</span>
                        </div>
                    </div>

                    {{-- TOTAL A PAGAR --}}
                    <div
                        class="flex flex-col items-end justify-center rounded-none bg-amber-400 px-8 border-4 border-amber-600 shadow-2xl min-w-[200px] h-[72px]">
                        <span
                            class="text-[11px] uppercase font-black text-amber-900 tracking-widest leading-none mb-1">PAGAR
                            TOTAL</span>
                        <div class="flex items-baseline gap-1.5 text-black">
                            <span class="font-black text-5xl leading-none">{{ number_format($total ?? 0, 2) }}</span>
                            <span class="font-black text-2xl text-amber-900">€</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overlay de Sesión Cerrada --}}
        @if (!$isSessionOpen)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-sm px-4">
                <div class="bg-white rounded-none shadow-2xl max-w-md w-full p-8 border-t-8 border-primary-600">
                    <div class="text-center mb-6">
                        <div
                            class="bg-primary-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-heroicon-o-lock-open class="w-10 h-10 text-primary-600" />
                        </div>
                        <h2 class="text-2xl font-black text-gray-900 uppercase">Apertura de Caja</h2>
                        <p class="text-gray-500 text-sm mt-1">Debe iniciar una sesión de venta para operar el TPV.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Fondo
                                de Apertura
                                (€)</label>
                            <input type="number" wire:model="openingFund" step="0.01"
                                class="w-full h-14 text-center text-3xl font-black border-2 border-gray-300 rounded-none focus:border-primary-500 focus:ring-primary-500 bg-white !text-black" />
                        </div>

                        <button wire:click="openSession" type="button"
                            class="w-full py-4 bg-primary-600 hover:bg-primary-700 text-white rounded-none font-black text-lg uppercase shadow-xl transition active:scale-95 flex items-center justify-center gap-2">
                            <x-heroicon-o-play class="w-6 h-6" /> ABRIR DÍA / VENTA
                        </button>

                        <button wire:click="salirPos" type="button"
                            class="w-full py-2 text-gray-500 hover:text-gray-700 font-bold text-sm uppercase">
                            Cancelar y Volver
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal de Cierre (Arqueo) --}}
        @if ($showClosingModal)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-sm px-4 py-8 overflow-y-auto">
                <div
                    class="bg-white rounded-none shadow-2xl max-w-4xl w-full p-0 border-t-8 border-red-600 flex flex-col max-h-full">
                    {{-- Header Modal --}}
                    <div
                        class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-none shrink-0">
                        <div>
                            <h2 class="text-xl font-black text-gray-900 uppercase">Cierre de Caja (Arqueo)</h2>
                            <p class="text-[10px] text-gray-500 font-bold">FECHA: {{ now()->format('d/m/Y H:i') }}
                                | USUARIO:
                                {{ auth()->user()->name }}</p>
                        </div>
                        <button wire:click="$set('showClosingModal', false)"
                            class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                    </div>

                    <div class="p-6 overflow-y-auto grid md:grid-cols-2 gap-8">
                        {{-- Columna Izquierda: Desglose de Monedas --}}
                        <div>
                            <h3 class="text-xs font-black text-gray-400 uppercase mb-4 border-b pb-1">Desglose de
                                Efectivo</h3>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                                @foreach ($cashBreakdown as $cents => $qty)
                                    <div class="flex items-center gap-2">
                                        <span class="shrink-0 font-bold text-gray-600 text-xs text-right min-w-[3rem]">
                                            @if ((int) $cents >= 500)
                                                <span
                                                    class="bg-gray-100 px-1.5 py-0.5 rounded border border-gray-300 whitespace-nowrap w-auto inline-block">{{ number_format($cents / 100, 0) }}€</span>
                                            @else
                                                <span
                                                    class="text-amber-700 whitespace-nowrap w-auto inline-block font-black">{{ number_format($cents / 100, 2) }}€</span>
                                            @endif
                                        </span>
                                        <input type="number" wire:model.live="cashBreakdown.{{ $cents }}"
                                            class="w-full h-8 text-center text-sm font-bold border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500 px-1"
                                            placeholder="0" min="0" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Columna Derecha: Resumen y Cuadre --}}
                        <div class="space-y-6">
                            <div class="bg-gray-100 p-4 rounded-none border border-gray-200">
                                <h3
                                    class="text-[10px] font-black text-gray-500 uppercase mb-3 text-center tracking-widest leading-none">
                                    Cálculo del Sistema</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Fondo de Apertura:</span>
                                        <span
                                            class="font-bold text-gray-900">{{ number_format($activeSession->fondo_apertura, 2) }}
                                            €</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Ventas en Efectivo:</span>
                                        <span class="font-bold text-green-600">+
                                            {{ number_format($activeSession->total_tickets_efectivo, 2) }} €</span>
                                    </div>
                                    <div class="border-t border-gray-300 pt-2 flex justify-between">
                                        <span class="font-black text-xs uppercase text-gray-700">Total
                                            Teórico:</span>
                                        <span
                                            class="font-black text-lg text-gray-900 underline decoration-primary-500">{{ number_format($activeSession->fondo_apertura + $activeSession->total_tickets_efectivo, 2) }}
                                            €</span>
                                    </div>
                                    <div
                                        class="flex justify-between text-sm pt-4 border-t border-dashed border-gray-300">
                                        <span class="text-gray-600">Ventas en Tarjeta:</span>
                                        <span
                                            class="font-bold text-blue-600">{{ number_format($activeSession->total_tickets_tarjeta, 2) }}
                                            €</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-primary-50 p-4 rounded-none border-2 border-primary-200">
                                <h3
                                    class="text-[10px] font-black text-primary-600 uppercase mb-2 text-center tracking-widest leading-none">
                                    TOTAL EFECTIVO REAL</h3>
                                <div class="text-center">
                                    <span
                                        class="text-4xl font-black text-primary-700 leading-none">{{ number_format($realFinalCash, 2) }}
                                        €</span>
                                </div>

                                @php
                                    $teorico = $activeSession->fondo_apertura + $activeSession->total_tickets_efectivo;
                                    $diff = round($realFinalCash - $teorico, 2);
                                @endphp

                                <div
                                    class="mt-4 pt-3 border-t border-primary-200 flex justify-center items-center gap-2">
                                    <span
                                        class="text-xs font-bold uppercase {{ $diff == 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-600' : 'text-amber-600') }}">
                                        Desfase: {{ number_format($diff, 2) }} €
                                    </span>
                                    @if ($diff == 0)
                                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-600" />
                                    @elseif($diff < 0)
                                        <x-heroicon-s-x-circle class="w-5 h-5 text-red-600" />
                                    @else
                                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-600" />
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Observaciones
                                    /
                                    Notas</label>
                                <textarea wire:model="sessionNotes" rows="3"
                                    class="w-full text-sm border-gray-300 rounded-none focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Escribe alguna observación sobre el arqueo si es necesario..."></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Modal --}}
                    <div
                        class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3 rounded-none shrink-0">
                        <button wire:click="$set('showClosingModal', false)" type="button"
                            class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-none font-bold text-sm uppercase hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button wire:click="confirmSessionClosure" type="button"
                            class="px-8 py-2.5 bg-red-600 !hover:bg-red-700 !ext-gray-700 rounded-none font-black text-sm uppercase shadow-lg transition active:scale-95 flex items-center gap-2">
                            <x-heroicon-o-check class="w-5 h-5" /> Confirmar Arqueo y Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal de Previsualización e Impresión --}}
        @if ($showPrintModal)
            <div
                class="fixed inset-0 z-[999] flex items-center justify-center bg-gray-900/90 backdrop-blur-sm px-4 py-8">
                <div class="bg-white rounded-none shadow-2xl max-w-xl w-full p-0 flex flex-col h-full max-h-[90vh] border-t-8 border-primary-600 overflow-hidden"
                    x-data="{
                        print() {
                            const iframe = document.getElementById('print-iframe');
                            if (iframe && iframe.contentWindow) {
                                try {
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                } catch (e) { console.error('Error printing:', e); }
                            }
                        }
                    }" @keydown.enter.window="print()">

                    {{-- Header Modal --}}
                    <div
                        class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 shrink-0">
                        <div>
                            <h2 class="text-xl font-black text-gray-900 uppercase">Imprimir Ticket</h2>
                            <p class="text-[10px] text-gray-500 font-bold uppercase">Previsualización del PDF</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="{{ $printUrl }}" target="_blank"
                                class="text-[10px] text-primary-600 hover:text-primary-800 font-black underline uppercase">Abrir
                                en pestaña nueva</a>
                            <button wire:click="$set('showPrintModal', false)"
                                class="text-gray-400 hover:text-red-600 transition-colors">
                                <x-heroicon-m-x-mark class="w-8 h-8" />
                            </button>
                        </div>
                    </div>

                    {{-- Contenido (Iframe) --}}
                    <div class="flex-1 bg-gray-200 p-2 md:p-4 overflow-hidden relative">
                        <iframe id="print-iframe" wire:key="iframe-{{ $printUrl }}" src="{{ $printUrl }}"
                            onload="setTimeout(() => { try { this.contentWindow.focus(); this.contentWindow.print(); } catch(e){} }, 2000);"
                            class="w-full h-full rounded-none shadow-lg bg-white border-0"></iframe>

                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-3 shrink-0">
                        <button wire:click="$set('showPrintModal', false)" type="button"
                            class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-none font-bold text-sm uppercase hover:bg-gray-100 transition">
                            Cerrar
                        </button>

                        <button @click="print()" type="button"
                            class="flex-1 py-4 bg-green-600 hover:bg-green-700 text-white rounded-none font-black text-lg uppercase shadow-xl transition active:scale-95 flex items-center justify-center gap-2">
                            <x-heroicon-s-printer class="w-6 h-6" /> IMPRIMIR (ENTER)
                        </button>
                    </div>
                </div>
            </div>
        @endif
</x-filament-panels::page>
