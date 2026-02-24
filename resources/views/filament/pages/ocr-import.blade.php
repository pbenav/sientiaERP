<x-filament-panels::page>
    <style>
        /* Ocultar spin buttons en campos numéricos */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }

        /* Asegurar que el contenido de la tabla no se envuelva */
        .ocr-table th,
        .ocr-table td {
            white-space: nowrap;
        }

        /* Hacer que la tabla sea scrollable horizontalmente si es necesario */
        .table-container {
            overflow-x: auto;
        }

        /* Mayúsculas globales si está habilitado */
        .uppercase-display {
            text-transform: uppercase;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navegación con Enter: avanzar al siguiente campo
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && (e.target.tagName === 'INPUT' || e.target.tagName ===
                        'TEXTAREA')) {
                    e.preventDefault();

                    // Obtener todos los inputs editables
                    const inputs = Array.from(document.querySelectorAll(
                        'input:not([type="hidden"]):not([disabled]), textarea:not([disabled])'));
                    const currentIndex = inputs.indexOf(e.target);

                    if (currentIndex > -1 && currentIndex < inputs.length - 1) {
                        // Enfocar el siguiente input
                        inputs[currentIndex + 1].focus();
                    }
                }
            });
        });
    </script>
    <div class="space-y-1">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-1">
            {{ $this->form }}
            <p class="text-xs text-gray-500 text-right mt-2">Límite: {{ $maxUploadSize }}</p>

            @if (!$showDataForm)
                <div class="flex justify-end mt-4">
                    <x-filament::button wire:click="processImage" wire:loading.attr="disabled">
                        <span wire:loading.remove>Procesar Imagen</span>
                        <span wire:loading>Procesando...</span>
                    </x-filament::button>
                </div>
            @endif
        </div>

        @if ($showDataForm)
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-1 space-y-1">
                <h3 class="text-sm font-semibold">Datos Detectados - Revisa y Edita</h3>

                <div class="grid grid-cols-2 gap-0">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                        <input type="date" wire:model="parsedData.date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº
                            Documento</label>
                        <input type="text" wire:model="parsedData.document_number"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <select wire:model="parsedData.supplier_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white {{ $displayUppercase ? 'uppercase-display' : '' }}">
                            <option value="">-- Seleccionar Proveedor --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->nombre_comercial }} @if ($supplier->nif_cif)
                                        ({{ $supplier->nif_cif }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if (!empty($parsedData['supplier']))
                            <p class="text-xs text-gray-500 mt-1">Detectado por OCR: {{ $parsedData['supplier'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIF/CIF</label>
                        <input type="text" wire:model="parsedData.nif"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                </div>

                @if (!empty($parsedData['items']))
                    <div class="mt-1">
                        <div class="flex justify-between items-center mb-1">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Líneas de Productos</h4>
                            <x-filament::button size="xs" color="gray" wire:click="addItem">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Añadir Línea
                            </x-filament::button>
                        </div>
                        <div class="overflow-x-auto table-container">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 ocr-table">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-1 py-0 text-center w-8">
                                            <input type="checkbox" wire:model.live="print_all_labels"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        </th>
                                        <th
                                            class="px-1 py-0 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                                            Referencia/SKU</th>
                                        <th
                                            class="px-1 py-0 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            Descripción</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-16">
                                            Cant.</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                                            P. Compra</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-16">
                                            Dto %</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-20">
                                            Margen %</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                                            Beneficio</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-20">
                                            IVA</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                                            P. Venta</th>
                                        <th
                                            class="px-1 py-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">
                                            Total Línea</th>
                                        <th class="px-1 py-0 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @foreach ($parsedData['items'] as $index => $item)
                                        <tr class="{{ !empty($item['matched_product_id']) ? 'bg-yellow-50 dark:bg-yellow-900/20' : '' }}"
                                            x-data="{
                                                index: {{ $index }},
                                                updatePriceFromMargin() {
                                                    const purchasePrice = parseFloat(this.$refs.purchasePrice.value) || 0;
                                                    const discount = parseFloat(this.$refs.discount.value) || 0;
                                                    const margin = parseFloat(this.$refs.margin.value) || 0;
                                                    const currentTaxRate = parseFloat(this.$refs.taxRate.value) || 21;
                                            
                                                    const netCost = purchasePrice * (1 - (discount / 100));
                                            
                                                    if (margin >= 100) {
                                                        this.$refs.margin.value = 99;
                                                        return;
                                                    }
                                            
                                                    const priceWithoutVat = netCost / (1 - (margin / 100));
                                                    const benefit = priceWithoutVat - netCost;
                                                    const vatAmount = priceWithoutVat * (currentTaxRate / 100);
                                                    const salePrice = priceWithoutVat * (1 + (currentTaxRate / 100));
                                            
                                                    this.$refs.salePrice.value = salePrice.toFixed(2);
                                                    $wire.set('parsedData.items.{{ $index }}.benefit', parseFloat(benefit.toFixed(2)));
                                                    $wire.set('parsedData.items.{{ $index }}.vat_amount', parseFloat(vatAmount.toFixed(2)));
                                                    $wire.set('parsedData.items.{{ $index }}.sale_price', parseFloat(salePrice.toFixed(2)));
                                                    $wire.set('parsedData.items.{{ $index }}.vat_rate', currentTaxRate);
                                                },
                                            
                                                updateMarginFromPrice() {
                                                    const purchasePrice = parseFloat(this.$refs.purchasePrice.value) || 0;
                                                    const discount = parseFloat(this.$refs.discount.value) || 0;
                                                    const salePrice = parseFloat(this.$refs.salePrice.value) || 0;
                                                    const currentTaxRate = parseFloat(this.$refs.taxRate.value) || 21;
                                            
                                                    const netCost = purchasePrice * (1 - (discount / 100));
                                            
                                                    if (salePrice > 0 && netCost >= 0) {
                                                        const priceWithoutVat = salePrice / (1 + (currentTaxRate / 100));
                                                        const margin = (1 - (netCost / priceWithoutVat)) * 100;
                                                        const benefit = priceWithoutVat - netCost;
                                                        const vatAmount = priceWithoutVat * (currentTaxRate / 100);
                                            
                                                        this.$refs.margin.value = margin.toFixed(2);
                                                        $wire.set('parsedData.items.{{ $index }}.margin', parseFloat(margin.toFixed(2)));
                                                        $wire.set('parsedData.items.{{ $index }}.benefit', parseFloat(benefit.toFixed(2)));
                                                        $wire.set('parsedData.items.{{ $index }}.vat_amount', parseFloat(vatAmount.toFixed(2)));
                                                        $wire.set('parsedData.items.{{ $index }}.vat_rate', currentTaxRate);
                                                    } else if (salePrice > 0 && netCost === 0) {
                                                        this.$refs.margin.value = 100;
                                                        $wire.set('parsedData.items.{{ $index }}.margin', 100);
                                                    }
                                                }
                                            }">
                                            <td class="px-1 py-0 text-center">
                                                <input type="checkbox"
                                                    wire:model.live="parsedData.items.{{ $index }}.print_label"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="text"
                                                    wire:model="parsedData.items.{{ $index }}.reference"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white {{ $displayUppercase ? 'uppercase-display' : '' }}"
                                                    placeholder="REF">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="text"
                                                    wire:model="parsedData.items.{{ $index }}.description"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white {{ $displayUppercase ? 'uppercase-display' : '' }}">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01"
                                                    wire:model="parsedData.items.{{ $index }}.quantity"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01"
                                                    wire:model="parsedData.items.{{ $index }}.unit_price"
                                                    x-ref="purchasePrice" @input="updatePriceFromMargin()"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01"
                                                    wire:model="parsedData.items.{{ $index }}.discount"
                                                    x-ref="discount" @input="updatePriceFromMargin()"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    placeholder="0">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01" min="0" max="1000"
                                                    wire:model="parsedData.items.{{ $index }}.margin"
                                                    x-ref="margin" @input="updatePriceFromMargin()"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    placeholder="30">
                                            </td>
                                            <td class="px-1 py-0 text-xs text-right font-medium dark:text-white">
                                                @php
                                                    $purchasePrice = (float) ($item['unit_price'] ?? 0);
                                                    $discount = (float) ($item['discount'] ?? 0);
                                                    $netCost = $purchasePrice * (1 - $discount / 100);
                                                    $margin = (float) ($item['margin'] ?? 30);
                                                    if ($margin >= 100) {
                                                        $margin = 99;
                                                    }
                                                    $priceWithoutVat = $netCost / (1 - $margin / 100);
                                                    $benefit = $priceWithoutVat - $netCost;
                                                @endphp
                                                {{ number_format($benefit, 2) }} €
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01"
                                                    wire:model="parsedData.items.{{ $index }}.vat_rate"
                                                    x-ref="taxRate" @input="updatePriceFromMargin()"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    placeholder="21">
                                            </td>
                                            <td class="px-1 py-0">
                                                <input type="number" step="0.01"
                                                    wire:model="parsedData.items.{{ $index }}.sale_price"
                                                    x-ref="salePrice" @input="updateMarginFromPrice()"
                                                    onfocus="this.select()"
                                                    class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            </td>
                                            <td class="px-1 py-0 text-xs text-right font-medium dark:text-white">
                                                @php
                                                    $quantity = (float) ($item['quantity'] ?? 1);
                                                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                                                    $discount = (float) ($item['discount'] ?? 0);
                                                    $subtotal = $quantity * $unitPrice;
                                                    if ($discount > 0) {
                                                        $subtotal = $subtotal * (1 - $discount / 100);
                                                    }
                                                @endphp
                                                {{ number_format($subtotal, 2) }} €
                                            </td>
                                            <td class="px-1 py-0 text-center">
                                                <button type="button" wire:click="removeItem({{ $index }})"
                                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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

                <div
                    class="p-1 bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 rounded-lg flex flex-col md:flex-row md:items-center gap-1">
                    <div class="flex items-center gap-1">
                        <input type="checkbox" id="generateLabels" wire:model.live="generateLabels"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <label for="generateLabels"
                            class="text-xs font-medium text-gray-700 dark:text-gray-300">Generar documento de etiquetas
                            también</label>
                    </div>

                    @if ($generateLabels)
                        <div class="flex-1 max-w-xs">
                            <select wire:model="selectedLabelFormatId"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">-- Seleccionar Formato --</option>
                                @foreach ($labelFormats as $format)
                                    <option value="{{ $format->id }}">{{ $format->nombre }}
                                        ({{ $format->ancho_etiqueta }}x{{ $format->alto_etiqueta }}mm)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-16" title="Fila de inicio">
                            <input type="number" wire:model="startRow" min="1"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div class="w-16" title="Columna de inicio">
                            <input type="number" wire:model="startColumn" min="1"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-1 pt-1 border-t dark:border-gray-700">
                    <x-filament::button color="gray" size="xs" wire:click="$set('showDataForm', false)">
                        Nueva Imagen
                    </x-filament::button>
                    <x-filament::button size="xs" wire:click="createDocument" wire:loading.attr="disabled">
                        <span wire:loading.remove>Crear Albarán</span>
                        <span wire:loading>Creando...</span>
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
