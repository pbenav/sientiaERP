<div class="space-y-4">
    @if ($getLabel())
        <h3 class="text-lg font-semibold">{{ $getLabel() }}</h3>
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    @if ($isReorderable())
                        <th class="px-3 py-2 w-10"></th>
                    @endif
                    
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Producto</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Código</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Cant.</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Precio</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Dto. %</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">IVA %</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Total</th>
                    <th class="px-3 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($getState() ?? [] as $uuid => $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800" wire:key="{{ $uuid }}">
                        @if ($isReorderable())
                            <td class="px-3 py-2">
                                <button type="button" class="text-gray-400 hover:text-gray-600 cursor-move" wire:sortable.handle>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                    </svg>
                                </button>
                            </td>
                        @endif
                        
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('product_id') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('codigo') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('cantidad') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('precio_unitario') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('descuento') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('iva') }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $getChildComponentContainer($uuid)->getComponent('total') }}
                        </td>
                        <td class="px-3 py-2">
                            @if (! $isDisabled())
                                <button
                                    type="button"
                                    wire:click="dispatchFormEvent('repeater::deleteItem', '{{ $getStatePath() }}', '{{ $uuid }}')"  
                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">
                            No hay líneas
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (! $isDisabled())
        <div class="flex justify-start">
            <button
                type="button"
                wire:click="dispatchFormEvent('repeater::createItem', '{{ $getStatePath() }}')"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ $getAddActionLabel() ?? 'Añadir línea' }}
            </button>
        </div>
    @endif
</div>
