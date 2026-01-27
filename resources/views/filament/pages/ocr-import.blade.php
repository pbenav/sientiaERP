<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            {{ $this->form }}
            <p class="text-xs text-gray-500 text-right mt-2">Límite: {{ $maxUploadSize }}</p>

            @if(!$showDataForm)
                <div class="flex justify-end mt-4">
                    <x-filament::button wire:click="processImage" wire:loading.attr="disabled">
                        <span wire:loading.remove>Procesar Imagen</span>
                        <span wire:loading>Procesando...</span>
                    </x-filament::button>
                </div>
            @endif
        </div>

        @if($showDataForm)
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold">Datos Detectados - Revisa y Edita</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                        <input type="date" wire:model="parsedData.date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Documento</label>
                        <input type="text" wire:model="parsedData.document_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <input type="text" wire:model="parsedData.supplier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Nombre del proveedor">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIF/CIF</label>
                        <input type="text" wire:model="parsedData.nif" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                </div>

                @if(!empty($parsedData['items']))
                    <div class="mt-6">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-medium text-gray-900 dark:text-white">Líneas de Productos</h4>
                            <x-filament::button size="sm" color="gray" wire:click="addItem">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Añadir Línea
                            </x-filament::button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">Ref/SKU</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">Cantidad</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">P. Compra</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">Margen %</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">PVP</th>
                                        <th class="px-3 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @foreach($parsedData['items'] as $index => $item)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <input type="text" wire:model="parsedData.items.{{ $index }}.reference" class="block w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="REF">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" wire:model="parsedData.items.{{ $index }}.description" class="block w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" wire:model="parsedData.items.{{ $index }}.quantity" class="block w-full text-sm rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" wire:model="parsedData.items.{{ $index }}.unit_price" class="block w-full text-sm rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" min="0" max="1000" wire:model="parsedData.items.{{ $index }}.margin" class="block w-full text-sm rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="30">
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right font-medium dark:text-white">
                                                @php
                                                    $unitPrice = $item['unit_price'] ?? 0;
                                                    $margin = $item['margin'] ?? 30;
                                                    $pvp = $unitPrice * (1 + ($margin / 100));
                                                @endphp
                                                {{ number_format($pvp, 2) }} €
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" wire:click="removeItem({{ $index }})" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4 text-gray-500">
                        No se detectaron productos. Puedes añadirlos manualmente.
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-4 border-t dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="$set('showDataForm', false)">
                        Nueva Imagen
                    </x-filament::button>
                    <x-filament::button wire:click="createDocument" wire:loading.attr="disabled">
                        <span wire:loading.remove>Crear Albarán</span>
                        <span wire:loading>Creando...</span>
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
