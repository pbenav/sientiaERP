<div class="w-full" x-data>
    @if ($getLabel())
        <h3 class="text-lg font-semibold mb-3">{{ $getLabel() }}</h3>
    @endif

    <div class="w-full overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-600">
        <table class="w-full table-auto border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    @if ($isReorderable())
                        <th class="px-2 py-3 w-12 text-center border-b border-gray-300 dark:border-gray-600"></th>
                    @endif
                    
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Producto</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Código</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600 w-28">Cant.</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600 w-32">Precio</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600 w-24">Dto. %</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600 w-24">IVA %</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600 w-32">Total</th>
                    <th class="px-2 py-3 w-12 text-center border-b border-gray-300 dark:border-gray-600"></th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($getState() ?? [] as $uuid => $item)
                    @php
                        $container = $getChildComponentContainer($uuid);
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50" wire:key="{{ $getStatePath() }}-{{ $uuid }}">
                        @if ($isReorderable())
                            <td class="px-2 py-2 text-center border-b border-gray-200 dark:border-gray-700">
                                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-move" wire:sortable.handle>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                    </svg>
                                </button>
                            </td>
                        @endif
                        
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('product_id') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('codigo') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('cantidad') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('precio_unitario') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('descuento') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('iva') }}
                        </td>
                        <td class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $container->getComponent('total') }}
                        </td>
                        <td class="px-2 py-2 text-center border-b border-gray-200 dark:border-gray-700">
                            @unless ($isDisabled())
                                <button
                                    type="button"
                                    wire:click="dispatchFormEvent('repeater::deleteItem', '{{ $getStatePath() }}', '{{ $uuid }}')"  
                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            No hay líneas. Usa el botón "{{ $getAddActionLabel() ?? 'Añadir línea' }}" para agregar productos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @unless ($isDisabled())
        <div class="flex justify-start mt-3">
            <x-filament::button
                type="button"
                wire:click="dispatchFormEvent('repeater::createItem', '{{ $getStatePath() }}')"
                size="sm"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ $getAddActionLabel() ?? 'Añadir línea' }}
            </x-filament::button>
        </div>
    @endunless
</div>

