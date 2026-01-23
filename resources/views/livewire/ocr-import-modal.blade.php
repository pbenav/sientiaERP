<div>
    <div class="space-y-4">
    <div class="space-y-4">
        <!-- Filament Form with FileUpload -->
        {{ $this->form }}

        @if(!$rawText)
            <div class="flex justify-end">
                <button wire:click="processImage" wire:loading.attr="disabled" class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2">
                    <span wire:loading.remove wire:target="processImage">Procesar Imagen</span>
                    <span wire:loading wire:target="processImage">Procesando...</span>
                </button>
            </div>
        @endif

        <div wire:loading wire:target="data.documento" class="text-sm text-gray-500">
            Subiendo...
        </div>

        @if($rawText)
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Parsed Data Form -->
                <div class="space-y-2">
                    <p class="text-xs font-bold mb-1">Datos Detectados</p>
                    <div>
                        <label class="block text-xs text-gray-500">Fecha</label>
                        <input type="text" wire:model="parsedData.date" class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Total</label>
                        <input type="text" wire:model="parsedData.total" class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">NIF / CIF</label>
                        <input type="text" wire:model="parsedData.nif" class="w-full border-gray-300 rounded shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Proveedor</label>
                        <input type="text" wire:model="parsedData.supplier" class="w-full border-gray-300 rounded shadow-sm text-sm" placeholder="Nombre del proveedor">
                    </div>
                </div>
            </div>

            <!-- Parsed Items Table -->
            @if(!empty($parsedData['items']))
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
                            @foreach($parsedData['items'] as $index => $item)
                                <tr>
                                    <td class="px-2 py-1">{{ $item['description'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $item['quantity'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $item['unit_price'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Raw Text Area -->
            <div>
                <label class="block text-xs font-bold mb-1">Texto Completo (OCR)</label>
                <textarea wire:model="rawText" rows="6" class="w-full border-gray-300 rounded shadow-sm text-xs font-mono"></textarea>
            </div>
            
            <div class="flex justify-between pt-4">
                <button wire:click="resetState" class="text-gray-600 hover:text-gray-800 font-medium py-2 px-4 rounded border border-gray-300 hover:bg-gray-50">
                    Nueva Imagen
                </button>
                <button wire:click="confirm" class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded shadow">
                    Crear Albarán con estos datos
                </button>
            </div>
        @endif
    </div>
</div>
