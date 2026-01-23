<div>
    <div class="space-y-4">
        @if(!$rawText)
            <div class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-300 rounded-lg">
                <input type="file" wire:model="file" accept="image/*" class="block w-full text-sm text-slate-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-full file:border-0
                    file:text-sm file:font-semibold
                    file:bg-primary-50 file:text-primary-700
                    hover:file:bg-primary-100
                "/>
                <div wire:loading wire:target="file" class="mt-2 text-sm text-gray-500">
                    Procesando imagen...
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Preview (Optional, showing the uploaded image) -->
                @if ($file)
                    <div class="border rounded p-2">
                        <p class="text-xs font-bold mb-1">Imagen Subida</p>
                        <img src="{{ $file->temporaryUrl() }}" class="max-h-64 object-contain">
                    </div>
                @endif
                
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
            
            <div class="flex justify-end pt-4">
                <button wire:click="confirm" class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded shadow">
                    Crear Albarán con estos datos
                </button>
            </div>
        @endif
    </div>
</div>
