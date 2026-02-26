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

            /* Forzar visibilidad absoluta en controles del TPV */
            .pos-label-force {
                color: #000000 !important;
                font-weight: 900 !important;
                display: block !important;
            }

            .pos-input-force {
                color: #000000 !important;
                background-color: #ffffff !important;
            }

            .pos-btn-inactive-force {
                color: #000000 !important;
                background-color: #f3f4f6 !important;
                /* gray-100 */
                border-color: #d1d5db !important;
                /* gray-300 */
            }

            .pos-btn-inactive-force svg {
                color: #000000 !important;
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
        class="flex flex-col bg-white border border-gray-200 shadow-sm font-sans text-sm text-gray-900 h-[calc(100vh-170px)] md:h-[calc(100vh-200px)] overflow-hidden md:rounded-lg">

        {{-- Barra de Navegación Profesional --}}
        <div class="bg-gray-800 text-white px-4 py-2 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-computer-desktop class="w-5 h-5 text-primary-400" />
                    <span class="font-black text-lg tracking-tight">SIENTIA <span
                            class="text-primary-400">POS</span></span>
                </div>
                <div
                    class="hidden md:flex items-center gap-3 px-3 py-1 bg-gray-700/50 rounded-full border border-white/10">
                    <div class="flex items-center gap-1.5">
                        <x-heroicon-s-user class="w-3.5 h-3.5 text-gray-400" />
                        <span class="text-[10px] uppercase font-bold text-gray-300">{{ auth()->user()->name }}</span>
                    </div>
                    <div class="w-px h-3 bg-white/10"></div>
                    <div class="flex items-center gap-1.5">
                        <div
                            class="w-2 h-2 rounded-full {{ $isSessionOpen ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}">
                        </div>
                        <span
                            class="text-[10px] uppercase font-bold {{ $isSessionOpen ? 'text-green-400' : 'text-red-400' }}">
                            Sesión {{ $isSessionOpen ? 'Abierta' : 'Cerrada' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($isSessionOpen)
                    <button wire:click="openClosingModal" type="button"
                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-gray-900 rounded-md font-black text-[11px] uppercase transition shadow-lg active:scale-95">
                        <x-heroicon-s-banknotes class="w-4 h-4 group-hover:rotate-12 transition-transform" />
                        CIERRE / ARQUEO
                    </button>
                @endif

                <button wire:click="salirPos" type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-700 hover:bg-red-600 text-white rounded-md font-black text-[11px] uppercase transition shadow-lg active:scale-95 border border-white/10">
                    <x-heroicon-s-arrow-left-on-rectangle class="w-4 h-4" />
                    SALIR (TPV)
                </button>
            </div>
        </div>

        {{-- Header Compacto --}}
        <div class="bg-white border-b border-gray-200 px-3 md:px-4 py-2 shrink-0 shadow-sm">
            {{-- Fila 1: Datos del ticket y cliente --}}
            <div class="flex items-center gap-2 md:gap-4 mb-2">
                {{-- Número --}}
                <div class="w-24 md:w-32">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Número</label>
                    <input type="text" value="{{ $this->data['numero'] ?? 'AUTO' }}" readonly
                        class="w-full h-9 bg-gray-50 border border-gray-300 rounded px-2 text-sm font-mono font-bold text-gray-900" />
                </div>

                {{-- Fecha --}}
                <div class="w-32 md:w-40">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Fecha</label>
                    <input type="date" wire:model="fecha" id="pos-fecha"
                        class="w-full h-9 border-gray-300 rounded px-2 text-sm font-bold text-gray-900 focus:ring-primary-500 focus:border-primary-500" />
                </div>

                {{-- Cliente --}}
                <div class="flex-1">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Cliente</label>
                    <div class="relative">
                        <select wire:model.live="nuevoClienteNombre" id="pos-cliente"
                            class="w-full h-9 border-gray-300 rounded px-2 text-sm font-bold text-gray-900 focus:ring-primary-500 focus:border-primary-500 appearance-none">
                            <option value="">Selecciona un cliente...</option>
                            @foreach ($resultadosClientes as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Teléfono oculto en móvil --}}
                <div class="hidden lg:block w-40">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Teléfono</label>
                    <input type="text" value="{{ $this->clienteTelefono }}" readonly
                        class="pos-input-force w-full h-9 border border-gray-300 rounded px-2 text-sm font-bold" />
                </div>
            </div>

            {{-- Fila 2: TPV Buttons & Session Control --}}
            <div class="flex gap-1 items-center">
                <div class="flex gap-1 flex-1">
                    @foreach (range(1, 4) as $tpv)
                        <button wire:click="cambiarTpv({{ $tpv }})" type="button"
                            class="flex-1 px-2 md:px-3 py-1.5 rounded text-xs font-black transition-all duration-200 shadow-md active:scale-95
                                       {{ (int) $tpvActivo === (int) $tpv
                                           ? 'bg-primary-600 text-white shadow-lg ring-4 ring-primary-300'
                                           : 'pos-btn-inactive-force border-2 hover:bg-gray-100 hover:shadow-lg' }}"
                            wire:loading.class="opacity-50 cursor-wait" wire:target="cambiarTpv">
                            TPV {{ $tpv }}
                        </button>
                    @endforeach
                </div>

                @if ($isSessionOpen)
                    <button wire:click="openClosingModal" type="button"
                        class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-bold shadow-md transition active:scale-95 flex items-center gap-1">
                        <x-heroicon-o-lock-closed class="w-4 h-4" /> CIERRE DE CAJA
                    </button>
                @endif

                <button type="button"
                    class="hidden md:flex px-3 py-1.5 pos-btn-inactive-force border-2 hover:bg-gray-100 rounded text-xs items-center justify-center font-black shadow-sm transition active:scale-95">
                    <x-heroicon-o-ticket class="w-3 h-3 mr-1" /> VALE
                </button>
            </div>
        </div>

        {{-- Área de Trabajo --}}
        <div class="flex-1 flex flex-col p-4 space-y-4 overflow-hidden bg-gray-50/50">

            {{-- Fila Única de Entrada --}}
            <div class="flex items-center space-x-2 bg-white p-3 rounded-lg border border-gray-200 shadow-sm shrink-0"
                x-data="{ focusNext(nextId) { setTimeout(() => document.getElementById(nextId)?.focus(), 100); } }" @focus-cantidad.window="focusNext('pos-cantidad')"
                @focus-precio.window="focusNext('pos-precio')" @focus-descuento.window="focusNext('pos-descuento')"
                @focus-codigo.window="focusNext('pos-codigo')">
                <div class="w-32">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Código</label>
                    <input type="text" wire:model.live="nuevoCodigo" wire:blur="buscarProducto(true)"
                        wire:keydown.enter.prevent="buscarProducto(true)" list="codigos-list" id="pos-codigo"
                        class="pos-input w-full h-9 border-gray-300 rounded px-2 font-mono text-sm focus:ring-primary-500 focus:border-primary-500 uppercase"
                        placeholder="SKU" autofocus />
                    <datalist id="codigos-list">
                        @foreach ($resultadosCodigo as $id => $sku)
                            <option value="{{ $sku }}">{{ $sku }}</option>
                        @endforeach
                    </datalist>
                </div>

                <div class="flex-1 min-w-[200px]">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none">Descripción</label>
                    <input type="text" wire:model.live="nuevoNombre" wire:blur="buscarProducto(true)"
                        wire:keydown.enter.prevent="buscarProducto(true)" list="productos-list" id="pos-descripcion"
                        class="pos-input w-full h-9 border-gray-300 rounded px-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Escribe para buscar..." />
                    <datalist id="productos-list">
                        @foreach ($resultadosNombre as $id => $nombre)
                            <option value="{{ $nombre }}">{{ $nombre }}</option>
                        @endforeach
                    </datalist>
                </div>

                <div class="w-20">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none text-right">Cant</label>
                    <input type="number" wire:model.live="nuevoCantidad"
                        x-on:keydown.enter.prevent="document.getElementById('pos-precio').focus()" id="pos-cantidad"
                        class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right font-bold text-gray-800 focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-24">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none text-right">Precio</label>
                    <input type="number" wire:model.live="nuevoPrecio"
                        x-on:keydown.enter.prevent="document.getElementById('pos-descuento').focus()" step="0.01"
                        id="pos-precio"
                        class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-16">
                    <label class="pos-label-force text-[10px] uppercase mb-1 leading-none text-right">Dto%</label>
                    <input type="number" wire:model.live="nuevoDescuento"
                        x-on:keydown.enter.prevent="document.getElementById('btn-anadir-producto').focus()"
                        step="0.01" id="pos-descuento"
                        class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-sm focus:ring-primary-500 focus:border-primary-500" />
                </div>

                <div class="w-32 bg-gray-50 rounded p-1 flex flex-col items-end justify-center border border-gray-200 h-10 px-6 mt-4"
                    style="margin-top: 20px;">
                    <span
                        class="font-black text-lg leading-none !text-black">{{ number_format($nuevoImporte, 2) }}</span>
                </div>

                <button wire:click="anotarLinea" wire:keydown.enter="anotarLinea" id="btn-anadir-producto"
                    tabindex="0"
                    class="pos-action mt-4 h-9 w-12 bg-primary-600 hover:bg-primary-500 text-white rounded shadow-sm flex items-center justify-center transition focus:ring-2 focus:ring-offset-1 focus:ring-primary-600"
                    style="margin-top: 20px;">
                    <x-heroicon-m-plus class="w-5 h-5" />
                </button>
            </div>

            {{-- Grid --}}
            <div class="flex-1 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-200 text-black text-xs uppercase sticky top-0">
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
                                    <td colspan="8" class="px-2 py-8 text-center text-gray-400">
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
            <div
                class="flex flex-col md:flex-row gap-4 shrink-0 bg-gray-100 p-3 rounded-lg border border-gray-300 shadow-inner">

                {{-- Panel Botones (Izquierda) - 2 Columnas Compacto --}}
                <div class="grid grid-cols-2 gap-2 w-fit shrink-0 overflow-visible"
                    style="display: grid !important; grid-template-columns: repeat(2, 1fr) !important; width: 172px !important;">
                    @foreach ([['Grabar', 'heroicon-o-check', 'from-green-600 to-green-700 border-green-700 text-white shadow-green-500/50'], ['Anular', 'heroicon-o-trash', 'from-red-600 to-red-700 border-red-700 text-white'], ['Imprimir', 'heroicon-o-printer', 'from-gray-700 to-gray-800 border-gray-900 text-white'], ['Nueva', 'heroicon-o-plus', 'from-amber-500 to-amber-600 border-amber-700 text-white'], ['Regalo', 'heroicon-o-gift', 'from-purple-600 to-purple-700 border-purple-800 text-white'], ['Salir', 'heroicon-o-arrow-right-on-rectangle', 'from-gray-800 to-gray-900 border-black text-white']] as $i => $btn)
                        <button
                            @if ($btn[0] === 'Grabar') wire:click="grabarTicket" 
                            wire:loading.attr="disabled"
                        @elseif($btn[0] === 'Imprimir')
                            wire:click="imprimirTicket"
                        @elseif($btn[0] === 'Anular')
                            wire:click="anularTicket"
                            wire:confirm="¿Estás seguro de que deseas anular/eliminar este ticket?"
                        @elseif($btn[0] === 'Nueva')
                            wire:click="nuevaVenta"
                        @elseif($btn[0] === 'Salir')
                            wire:click="salirPos" @endif
                            @if ($btn[0] === 'Grabar') onclick="this.style.background='linear-gradient(to bottom, #059669, #047857)'; this.style.color='white'; this.style.transform='scale(0.9)'; setTimeout(() => { this.style.background=''; this.style.color=''; this.style.transform=''; }, 300);" @endif
                            type="button"
                            class="flex flex-col items-center justify-center rounded border-2 shadow-md hover:shadow-lg transition-all duration-150 bg-gradient-to-b {{ $btn[2] }} w-20 h-20 shrink-0 {{ $btn[0] === 'Grabar' ? 'ring-2 ring-green-300 scale-105 z-10' : 'active:shadow-sm active:scale-95' }}">
                            <x-dynamic-component :component="$btn[1]" class="w-6 h-6 mb-1 text-white" />
                            <span
                                class="font-black text-[10px] leading-none text-center uppercase text-white">{{ $btn[0] }}</span>
                            @if ($btn[0] === 'Grabar')
                                <span wire:loading wire:target="grabarTicket"
                                    class="absolute inset-0 flex items-center justify-center bg-green-700 bg-opacity-90 rounded text-white font-bold text-[8px]">
                                    ESPERE...
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Panel Datos (Central) --}}
                <div class="flex-1 flex flex-col gap-4">
                    {{-- Descuentos (Arriba) --}}
                    <div
                        class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-300 shadow-sm h-fit">
                        <div class="flex flex-col">
                            <span class="pos-label-force text-[10px] uppercase">Dto Gral %</span>
                            <input type="number" wire:model.blur="descuento_general_porcentaje"
                                class="pos-input h-10 w-20 text-right border-gray-200 rounded bg-gray-50/50 font-bold text-lg focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col flex-1">
                            <span class="pos-label-force text-[10px] uppercase">Dto Gral €</span>
                            <input type="number" wire:model.blur="descuento_general_importe"
                                class="pos-input h-10 w-full text-right border-gray-200 rounded bg-gray-50/50 font-bold text-lg focus:ring-amber-500" />
                        </div>
                    </div>

                    {{-- Vendedor (Abajo) --}}
                    <div class="flex-1 space-y-3">
                        <div>
                            <span class="pos-label-force text-[10px] uppercase block mb-1">Vendedor</span>
                            <div
                                class="h-10 px-3 bg-white border border-gray-200 rounded flex items-center text-sm font-bold text-gray-700 shadow-sm">
                                {{ auth()->user()->name }}
                            </div>
                        </div>
                        <div>
                            <span class="pos-label-force text-[10px] uppercase block mb-1">Forma de
                                Pago</span>
                            <select wire:model.live="payment_method"
                                class="w-full h-10 border-gray-300 rounded text-sm bg-white font-black shadow-sm !text-black uppercase">
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
                    <div
                        class="flex flex-col items-end w-full rounded-lg bg-white p-3 border border-gray-300 shadow-sm min-h-[70px] justify-center overflow-hidden">
                        <span class="pos-label-force text-[10px] uppercase tracking-widest leading-none mb-1">Total
                            a Pagar</span>
                        <div class="flex items-baseline gap-1">
                            <span class="font-black tracking-tighter leading-none text-amber-500"
                                style="font-size: 30px !important; font-weight: 950 !important; line-height: 1 !important;">{{ number_format($total, 2) }}</span>
                            <span class="font-black text-amber-500" style="font-size: 18px !important;">€</span>
                        </div>
                    </div>

                    {{-- Entrega (Medio) --}}
                    <div class="flex flex-col bg-white p-3 rounded-lg border border-gray-300 shadow-sm">
                        <div class="flex justify-between items-center mb-1">
                            <span class="pos-label-force text-[11px] uppercase">Entrega Cliente €</span>
                            <button wire:click="dividirPago"
                                class="text-[10px] font-bold text-primary-600 hover:text-primary-800 flex items-center gap-1">
                                <x-heroicon-o-banknotes class="w-3 h-3" />
                                {{ $payment_method === 'mixed' ? 'Volver a único' : 'Dividir' }}
                            </button>
                        </div>

                        @if ($payment_method === 'mixed')
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-bold text-green-600 uppercase mb-0.5">Efectivo</span>
                                    <input type="number" wire:model.blur="pago_efectivo"
                                        class="pos-input h-10 w-full text-right font-black text-lg border-green-300 rounded bg-green-50 focus:ring-green-500" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-bold text-blue-600 uppercase mb-0.5">Tarjeta</span>
                                    <input type="number" wire:model.blur="pago_tarjeta"
                                        class="pos-input h-10 w-full text-right font-black text-lg border-blue-300 rounded bg-blue-50 focus:ring-blue-500" />
                                </div>
                            </div>
                        @else
                            <input type="number" wire:model.blur="entrega" id="pos-entrega"
                                class="pos-input h-10 w-full text-right font-black text-2xl border-gray-200 rounded bg-gray-50/50 shadow-inner focus:ring-primary-500" />
                        @endif
                    </div>

                    {{-- Cambio (Final) --}}
                    <div class="flex flex-col items-end bg-gray-100 px-4 py-2 rounded-lg border border-gray-200">
                        <span class="pos-label-force text-[10px] uppercase text-right leading-none mb-1">Cambio
                            a devolver</span>
                        <div class="flex items-baseline gap-1">
                            <span
                                class="font-black text-3xl text-gray-900 leading-none">{{ number_format(max(0, (float) $entrega - (float) $total), 2) }}</span>
                            <span class="text-lg font-black text-gray-700">€</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overlay de Sesión Cerrada --}}
        @if (!$isSessionOpen)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-sm px-4">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 border-t-8 border-primary-600">
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
                            <label class="pos-label-force text-xs uppercase mb-2">Fondo de Apertura
                                (€)</label>
                            <input type="number" wire:model="openingFund" step="0.01"
                                class="w-full h-14 text-center text-3xl font-black border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-primary-500 bg-white !text-black" />
                        </div>

                        <button wire:click="openSession" type="button"
                            class="w-full py-4 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-black text-lg uppercase shadow-xl transition active:scale-95 flex items-center justify-center gap-2">
                            <x-heroicon-o-play class="w-6 h-6" /> ABRIR DÍA / VENTA
                        </button>

                        <button wire:click="salirPos" type="button"
                            class="w-full py-2 !text-black font-black text-sm uppercase hover:underline">
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
                    class="bg-white rounded-xl shadow-2xl max-w-4xl w-full p-0 border-t-8 border-red-600 flex flex-col max-h-full">
                    {{-- Header Modal --}}
                    <div
                        class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl shrink-0">
                        <div>
                            <h2 class="text-xl font-black text-gray-900 uppercase">Cierre de Caja (Arqueo)</h2>
                            <p class="pos-label-force text-[10px]">FECHA: {{ now()->format('d/m/Y H:i') }}
                                | USUARIO:
                                {{ auth()->user()->name }}</p>
                        </div>
                        <button wire:click="$set('showClosingModal', false)"
                            class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                    </div>

                    <div class="p-6 overflow-y-auto grid md:grid-cols-2 gap-8">
                        {{-- Columna Izquierda: Desglose de Monedas --}}
                        <div>
                            <h3 class="pos-label-force text-xs uppercase mb-4 border-b pb-1">Desglose de
                                Efectivo</h3>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                                @foreach ($cashBreakdown as $val => $qty)
                                    <div class="flex items-center gap-2">
                                        <span class="w-16 text-right font-bold text-gray-600 text-xs">
                                            @if ((float) $val >= 5)
                                                <span
                                                    class="bg-gray-100 px-1.5 py-0.5 rounded border border-gray-300">{{ number_format($val, 0) }}
                                                    €</span>
                                            @else
                                                <span class="text-amber-700">{{ number_format($val, 2) }} €</span>
                                            @endif
                                        </span>
                                        <input type="number" wire:model.live="cashBreakdown.{{ $val }}"
                                            class="w-full h-8 text-center text-sm font-bold border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500 px-1"
                                            placeholder="0" min="0" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Columna Derecha: Resumen y Cuadre --}}
                        <div class="space-y-6">
                            <div class="bg-gray-100 p-4 rounded-xl border border-gray-200">
                                <h3
                                    class="pos-label-force text-[10px] uppercase mb-3 text-center tracking-widest leading-none">
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

                            <div class="bg-primary-50 p-4 rounded-xl border-2 border-primary-200">
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
                                <label class="pos-label-force text-[10px] uppercase mb-1">Observaciones
                                    /
                                    Notas</label>
                                <textarea wire:model="sessionNotes" rows="3"
                                    class="w-full text-sm border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Escribe alguna observación sobre el arqueo si es necesario..."></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Modal --}}
                    <div
                        class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3 rounded-b-xl shrink-0">
                        <button wire:click="$set('showClosingModal', false)" type="button"
                            class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg font-bold text-sm uppercase hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button wire:click="confirmSessionClosure" type="button"
                            class="px-8 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-black text-sm uppercase shadow-lg transition active:scale-95 flex items-center gap-2">
                            <x-heroicon-o-check class="w-5 h-5" /> Confirmar Arqueo y Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif
</x-filament-panels::page>
