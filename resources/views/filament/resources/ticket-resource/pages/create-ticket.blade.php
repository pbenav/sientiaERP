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
    class="flex flex-col bg-white border border-gray-200 shadow-sm font-sans text-sm text-gray-900 h-[85vh] overflow-hidden rounded-lg">
        
        {{-- Header Compacto --}}
        <div class="flex items-center bg-white border-b border-gray-200 px-4 py-2 space-x-4 shrink-0 shadow-sm">
            <div class="font-bold text-xl text-primary-600 whitespace-nowrap">TPV {{ $tpvActivo }}</div>
            
            <div class="flex items-center space-x-2 flex-1">
                <div class="flex items-center bg-gray-50 rounded px-3 py-1 border border-gray-200">
                    <span class="text-gray-500 text-xs mr-2 uppercase font-bold">Nº</span>
                    <span class="font-mono font-medium">{{ $this->data['numero'] ?? 'AUTO' }}</span>
                </div>
                
                <div class="flex items-center bg-gray-50 rounded px-3 py-1 border border-gray-200">
                    <span class="text-gray-500 text-xs mr-2 uppercase font-bold">Fecha</span>
                    <input type="date" wire:model="data.fecha" class="bg-transparent border-none text-gray-800 p-0 h-5 w-24 focus:ring-0 text-sm font-medium" />
                </div>

                <div class="flex space-x-1 ml-4 bg-gray-100 p-1 rounded-lg">
                    @foreach(range(1,4) as $tpv)
                        <button wire:click="cambiarTpv({{ $tpv }})" 
                                class="px-3 py-1 rounded-md text-xs font-bold transition {{ $tpvActivo === $tpv ? 'bg-white text-primary-600 shadow-sm ring-1 ring-gray-200' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200' }}">
                            T{{ $tpv }}
                        </button>
                    @endforeach
                </div>
                
                <div class="flex-1 flex items-center bg-gray-50 rounded px-3 py-1 border border-gray-200 ml-4">
                    <span class="text-gray-500 text-xs mr-2 uppercase font-bold">Cliente</span>
                    <div class="flex-1 h-5">
                       {{ $this->form->getComponent('customer_id') }}
                    </div>
                </div>
                
                 <button class="px-3 py-1 bg-white border border-gray-300 hover:bg-gray-50 rounded text-xs flex items-center text-gray-700 font-bold shadow-sm transition">
                    <x-heroicon-o-ticket class="w-3 h-3 mr-1 text-primary-500"/> VALE
                </button>
            </div>
        </div>

        {{-- Área de Trabajo --}}
        <div class="flex-1 flex flex-col p-4 space-y-4 overflow-hidden bg-gray-50/50">
            
            {{-- Fila Única de Entrada --}}
            <div class="flex items-center space-x-2 bg-white p-3 rounded-lg border border-gray-200 shadow-sm shrink-0">
                <div class="w-32">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Código</label>
                    <input type="text" wire:model.live="nuevoCodigo" wire:keydown.enter="buscarProducto" 
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 font-mono text-sm focus:ring-primary-500 focus:border-primary-500" 
                           placeholder="SKU" autofocus />
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none">Descripción</label>
                     <input type="text" list="productos-list" wire:model.live.debounce.300ms="nuevoNombre" wire:change="buscarProducto"
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-sm focus:ring-primary-500 focus:border-primary-500" 
                           placeholder="Buscar producto..." />
                     <datalist id="productos-list">
                         @foreach(\App\Models\Product::limit(20)->get() as $prod)
                             <option value="{{ $prod->sku }}">{{ $prod->name }}</option>
                         @endforeach
                     </datalist>
                </div>
                
                <div class="w-20">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Cant</label>
                    <input type="number" wire:model.live="nuevoCantidad" 
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right font-bold text-gray-800 focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-24">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Precio</label>
                    <input type="number" wire:model.live="nuevoPrecio" 
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-gray-800 focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-16">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1 leading-none text-right">Dto%</label>
                    <input type="number" wire:model.live="nuevoDescuento" 
                           class="pos-input w-full h-9 border-gray-300 rounded px-2 text-right text-gray-600 focus:ring-primary-500 focus:border-primary-500" />
                </div>
                
                <div class="w-32 bg-gray-50 rounded p-1 flex flex-col items-end justify-center border border-gray-200 h-10 px-2 mt-4">
                     <span class="font-bold text-lg leading-none text-primary-600">{{ number_format($nuevoImporte, 2) }}</span>
                </div>
                
                <button wire:click="anotarLinea" class="pos-action mt-4 h-9 w-12 bg-primary-600 hover:bg-primary-500 text-white rounded shadow-sm flex items-center justify-center transition focus:ring-2 focus:ring-offset-1 focus:ring-primary-600">
                    <x-heroicon-m-plus class="w-5 h-5"/>
                </button>
            </div>

            {{-- Grid --}}
            <div class="flex-1 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 font-bold sticky top-0 border-b border-gray-200 text-xs uppercase z-10">
                            <tr>
                                <th class="px-4 py-2 font-semibold tracking-wider">Código</th>
                                <th class="px-4 py-2 font-semibold tracking-wider">Descripción</th>
                                <th class="px-4 py-2 font-semibold tracking-wider text-right w-20">Cant.</th>
                                <th class="px-4 py-2 font-semibold tracking-wider text-right w-24">Precio</th>
                                <th class="px-4 py-2 font-semibold tracking-wider text-right w-16">Dto</th>
                                <th class="px-4 py-2 font-semibold tracking-wider text-right w-28">Total</th>
                                <th class="px-4 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($lineas as $idx => $linea)
                            <tr class="hover:bg-amber-50 cursor-pointer transition">
                                <td class="px-4 py-2 font-mono text-gray-500 text-xs">{{ $linea['codigo'] }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $linea['nombre'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $linea['cantidad'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($linea['precio'], 2) }}</td>
                                <td class="px-4 py-2 text-right text-gray-400">{{ $linea['descuento'] }}</td>
                                <td class="px-4 py-2 text-right font-bold text-gray-900">{{ number_format($linea['importe'], 2) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <button wire:click="eliminarLinea({{ $idx }})" class="text-gray-300 hover:text-red-500 transition">
                                        <x-heroicon-m-trash class="w-4 h-4"/>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div class="grid grid-cols-12 gap-4 h-24 shrink-0">
                
                {{-- Botones (Izquierda) --}}
                <div class="col-span-5 grid grid-cols-3 gap-2">
                     @foreach([
                        ['Grabar', 'Ctrl+G', 'heroicon-o-check', 'bg-white border-gray-200 text-green-600 hover:bg-green-50 hover:border-green-200'],
                        ['Imprimir', 'Ctrl+I', 'heroicon-o-printer', 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300'],
                        ['Cajón', 'Ctrl+A', 'heroicon-o-inbox', 'bg-white border-gray-200 text-amber-600 hover:bg-amber-50 hover:border-amber-200'],
                        ['Nueva', 'Ctrl+N', 'heroicon-o-plus', 'bg-white border-gray-200 text-blue-600 hover:bg-blue-50 hover:border-blue-200'],
                        ['Salir', 'Ctrl+S', 'heroicon-o-arrow-right-on-rectangle', 'bg-white border-gray-200 text-red-600 hover:bg-red-50 hover:border-red-200'],
                        ['Opciones', '', 'heroicon-o-cog-6-tooth', 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300'],
                     ] as $btn)
                     <button class="flex flex-col items-center justify-center rounded-lg border shadow-sm transition active:scale-95 {{ $btn[3] }}">
                        <x-dynamic-component :component="$btn[2]" class="w-6 h-6 mb-1"/>
                        <span class="font-bold text-xs">{{ $btn[0] }}</span>
                        @if($btn[1]) <span class="text-[9px] text-gray-400">{{ $btn[1] }}</span> @endif
                     </button>
                     @endforeach
                </div>

                {{-- Panel Totales (Derecha) --}}
                <div class="col-span-7 bg-white rounded-lg p-4 border border-gray-200 shadow-sm flex items-center justify-between">
                    <div class="flex-1 space-y-4">
                         <div class="flex items-center justify-between">
                             <span class="text-gray-500 font-medium text-sm w-24">ENTREGA</span>
                             <input type="number" wire:model.live="entrega" class="pos-input bg-gray-50 border border-gray-200 text-gray-900 text-right w-full h-10 px-3 rounded text-lg font-bold focus:ring-primary-500 focus:border-primary-500" />
                         </div>
                         <div class="flex items-center justify-between">
                             <span class="text-gray-500 font-medium text-xs uppercase tracking-wider w-24">Cambio</span>
                             <span class="font-bold text-2xl text-red-500">{{ number_format(max(0, $entrega - $total), 2) }}</span>
                         </div>
                    </div>
                    
                    <div class="w-px h-full bg-gray-200 mx-6"></div>
                    
                    <div class="text-right">
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total a Pagar</div>
                        <div class="text-5xl font-bold text-primary-600 tracking-tighter">{{ number_format($total, 2) }}<span class="text-2xl text-gray-400 ml-1">€</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
