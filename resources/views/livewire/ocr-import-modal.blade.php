    <div wire:ignore.self class="space-y-4">
        <!-- Filament Form with FileUpload -->
        {{ $this->form }}
        <p class="text-xs text-gray-500 text-right">Límite de subida del servidor: {{ $maxUploadSize }}</p>

        @if (!$showDataForm)
            <div class="flex justify-end">
                <button wire:click="processImage" wire:loading.attr="disabled" wire:target="processImage, data.documento"
                    class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="processImage">Procesar Imagen</span>
                    <span wire:loading wire:target="processImage">Procesando... (Espere)</span>
                </button>
            </div>
            <div class="text-xs text-gray-400 mt-2 text-right">
                <!-- Debug: showDataForm is FALSE -->
            </div>
        @else
            <!-- Debug: showDataForm is TRUE -->
        @endif

        <div wire:loading wire:target="data.documento" class="text-sm text-yellow-600 font-medium">
            Subiendo archivo, por favor espera...
        </div>

        <!-- Consola de Logs (Proceso de Creación) -->
        <div class="mt-4" x-data="{ showLogs: @entangle('isCreating') }" x-show="showLogs" x-transition>
            <div
                class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm h-48 overflow-y-auto shadow-inner border border-gray-700">
                <div class="flex items-center gap-2 mb-2 border-b border-gray-700 pb-1">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span class="font-bold">CONSOLA DE SISTEMA</span>
                </div>
                <div id="ocr-creation-logs" class="space-y-1 font-xs">
                    <!-- Stream -->
                </div>
            </div>
        </div>

        @if ($showDataForm)
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Parsed Data Form -->
                <div class="space-y-2">
                    <p class="text-xs font-bold mb-1">Datos Detectados</p>
                    <div>
                        <label class="block text-xs text-gray-500">Fecha</label>
                        <input type="text" wire:model="parsedData.date"
                            class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Total</label>
                        <input type="text" wire:model="parsedData.total"
                            class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">NIF / CIF</label>
                        <input type="text" wire:model="parsedData.nif"
                            class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Proveedor</label>
                        <input type="text" wire:model="parsedData.supplier"
                            class="w-full border-gray-300 rounded shadow-sm text-sm" placeholder="Nombre del proveedor">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Número Documento</label>
                        <input type="text" wire:model="parsedData.document_number"
                            class="w-full border-gray-300 rounded shadow-sm text-sm" placeholder="Ej: INV-2024-001">
                    </div>
                </div>
            </div>

            <!-- Parsed Items Table -->
            @if (!empty($parsedData['items']))
                <div class="border rounded overflow-hidden">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left">Concepto (Detectado)</th>
                                <th class="px-2 py-1 text-right">Cant.</th>
                                <th class="px-2 py-1 text-right">Precio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($parsedData['items'] as $index => $item)
                                <tr class="{{ !empty($item['matched_product_id']) ? 'bg-yellow-50' : '' }}">
                                    <td class="px-2 py-1">{{ $item['description'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $item['quantity'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $item['unit_price'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 border rounded bg-gray-50 text-center text-xs text-gray-500">
                    No se han detectado líneas de artículos automáticamente.
                </div>
            @endif

            <!-- Raw Text Area -->
            <div>
                <label class="block text-xs font-bold mb-1">Texto Completo (OCR)</label>
                <textarea wire:model="rawText" rows="6" class="w-full border-gray-300 rounded shadow-sm text-xs font-mono"></textarea>
            </div>

            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-2 bg-gray-50 dark:bg-gray-800 border rounded mt-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="modalGenerateLabels" wire:model.live="generateLabels"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <label for="modalGenerateLabels" class="text-xs font-bold text-gray-700 dark:text-gray-300">Generar
                        Etiquetas</label>
                </div>

                @if ($generateLabels)
                    <div class="flex flex-1 items-center gap-2">
                        <select wire:model="selectedLabelFormatId"
                            class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Formato --</option>
                            @foreach ($labelFormats as $format)
                                <option value="{{ $format->id }}">{{ $format->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="number" wire:model="startRow" min="1" title="Fila de inicio"
                            class="w-12 text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                        <input type="number" wire:model="startColumn" min="1" title="Columna de inicio"
                            class="w-12 text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between pt-4 border-t mt-4">
                <button x-on:click="$dispatch('close-modal', { id: 'importar' })" type="button"
                    class="text-gray-500 hover:text-gray-700 text-sm font-medium">
                    Cancelar
                </button>
                <div class="flex gap-2">
                    <button wire:click="resetState"
                        class="text-gray-600 hover:text-gray-800 font-medium py-2 px-4 rounded border border-gray-300 hover:bg-gray-50 text-sm">
                        Nueva Imagen
                    </button>
                    <button wire:click="createDocument" wire:loading.attr="disabled" wire:target="createDocument"
                        class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded shadow text-sm disabled:opacity-50">
                        <span wire:loading.remove wire:target="createDocument">Crear Albarán</span>
                        <span wire:loading wire:target="createDocument">Creando...</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
