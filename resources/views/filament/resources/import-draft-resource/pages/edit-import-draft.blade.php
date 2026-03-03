<x-filament-panels::page>
    <style>
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }

        .draft-table th,
        .draft-table td {
            white-space: nowrap;
        }

        .draft-table-wrap {
            overflow-x: auto;
        }
    </style>

    {{-- ── Status Banner ──────────────────────────────────────────────── --}}
    @if ($status === 'confirmed')
        <div
            class="rounded-lg border border-green-300 bg-green-50 dark:bg-green-900/20 p-3 mb-4 flex items-center gap-2">
            <span class="text-green-600 text-lg">✅</span>
            <span class="text-sm font-medium text-green-800 dark:text-green-200">
                Este borrador ya fue confirmado e integrado.
                @if ($draft->documento_id)
                    <a href="{{ route('filament.admin.resources.albaran-compras.edit', ['record' => $draft->documento_id]) }}"
                        class="underline ml-1">Ver albarán →</a>
                @endif
            </span>
        </div>
    @elseif ($status === 'rejected')
        <div class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 p-3 mb-4">
            <span class="text-sm font-medium text-red-700 dark:text-red-300">❌ Borrador rechazado.</span>
        </div>
    @endif

    <div class="space-y-4">
        {{-- ── Cabecera ───────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h3 class="text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">Datos del Albarán</h3>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                {{-- Proveedor --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:#6b7280; margin-bottom:2px;">Proveedor</label>
                    <select wire:model="matched_provider_id"
                        class="block w-full rounded border-gray-300 py-1 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                        <option value="">-- Sin asignar --</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s['id'] }}" @selected((int) $matched_provider_id === (int) $s['id'])>
                                {{ $s['nombre_comercial'] }} @if ($s['nif_cif'])
                                    ({{ $s['nif_cif'] }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @if ($provider_name)
                        <p class="text-[10px] text-gray-400 italic mt-0.5">IA detectó: {{ $provider_name }}</p>
                    @endif
                </div>
                {{-- Fecha --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:#6b7280; margin-bottom:2px;">Fecha</label>
                    <input type="date" wire:model="document_date"
                        class="block w-full rounded border-gray-300 py-1 text-xs shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                </div>
                {{-- Nº Documento --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:#6b7280; margin-bottom:2px;">Nº
                        Documento</label>
                    <input type="text" wire:model="document_number"
                        class="block w-full rounded border-gray-300 py-1 text-xs shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="Nº Albarán" {{ $status !== 'pending' ? 'disabled' : '' }}>
                </div>
                {{-- Subtotal --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:#6b7280; margin-bottom:2px;">Subtotal</label>
                    <input type="number" step="0.01" wire:model="subtotal"
                        class="block w-full rounded border-gray-300 py-1 text-xs shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                </div>
                {{-- Descuento --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:#6b7280; margin-bottom:2px;">Descuento</label>
                    <input type="number" step="0.01" wire:model="total_discount"
                        class="block w-full rounded border-gray-300 py-1 text-xs shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                </div>
                {{-- Total --}}
                <div>
                    <label
                        style="display:block; font-size:10px; font-weight:700; text-transform:uppercase; color: var(--fi-primary-600, #f59e0b); margin-bottom:2px;">TOTAL</label>
                    <input type="number" step="0.01" wire:model="total_amount"
                        style="font-weight:700; border-color: var(--fi-primary-500, #f59e0b); background-color: rgba(251,191,36,0.07);"
                        class="block w-full rounded py-1 text-xs shadow-sm"
                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                </div>
            </div>
        </div>

        {{-- ── Tabla de líneas ────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Líneas de Producto</h3>
                @if ($status === 'pending')
                    <x-filament::button size="xs" color="gray" wire:click="addItem">
                        + Añadir Línea
                    </x-filament::button>
                @endif
            </div>

            <div class="draft-table-wrap">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 draft-table text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-1 py-1 text-left text-gray-500 dark:text-gray-400 uppercase font-medium">
                                Referencia/SKU</th>
                            <th
                                class="px-1 py-1 text-left text-gray-500 dark:text-gray-400 uppercase font-medium min-w-[160px]">
                                Descripción</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-14">
                                Cant.</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-24">
                                P. Compra</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-14">
                                Dto%</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-20">
                                Margen%</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-24">
                                Beneficio</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-16">
                                IVA%</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-24">
                                PVP Final</th>
                            <th
                                class="px-1 py-1 text-right text-gray-500 dark:text-gray-400 uppercase font-medium w-24">
                                Total Línea</th>
                            <th class="px-1 py-1 w-16 text-center">Estado</th>
                            @if ($status === 'pending')
                                <th class="px-1 py-1 w-8"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @forelse ($items as $index => $item)
                            @php
                                $isExisting = !empty($item['matched_product_id']);
                                $existingProd = $item['existing_product'] ?? null;
                                $gross = (float) ($item['unit_price'] ?? 0);
                                $dto = (float) ($item['discount'] ?? 0);
                                $net = $gross * (1 - $dto / 100);
                                $mrg = (float) ($item['margin'] ?? 0);
                                $mrg = min($mrg, 99.99);
                                $priceNoVat = $net > 0 && $mrg < 100 ? $net / (1 - $mrg / 100) : 0;
                                $benefit = $priceNoVat - $net;
                                $qty = (float) ($item['quantity'] ?? 1);
                                $subtotal = $qty * $gross * (1 - $dto / 100);
                            @endphp
                            <tr class="{{ $isExisting ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}"
                                x-data="{
                                    benefit: {{ (float) ($item['benefit'] ?? $benefit) }},
                                    updateFromMargin() {
                                        const g = parseFloat(this.$refs.gross.value) || 0;
                                        const d = parseFloat(this.$refs.dto.value) || 0;
                                        const m = parseFloat(this.$refs.margin.value) || 0;
                                        const vat = parseFloat(this.$refs.vat.value) || 21;
                                        if (m >= 100) { this.$refs.margin.value = 99.99; return; }
                                        const net = g * (1 - d / 100);
                                        const noVat = net > 0 ? net / (1 - m / 100) : 0;
                                        const ben = noVat - net;
                                        const pvp = noVat * (1 + vat / 100);
                                        this.benefit = ben.toFixed(2);
                                        this.$refs.pvp.value = pvp.toFixed(2);
                                        $wire.set('items.{{ $index }}.sale_price', parseFloat(pvp.toFixed(2)));
                                        $wire.set('items.{{ $index }}.benefit', parseFloat(ben.toFixed(2)));
                                        $wire.set('items.{{ $index }}.vat_amount', parseFloat((noVat * vat / 100).toFixed(2)));
                                    },
                                    updateFromPvp() {
                                        const g = parseFloat(this.$refs.gross.value) || 0;
                                        const d = parseFloat(this.$refs.dto.value) || 0;
                                        const vat = parseFloat(this.$refs.vat.value) || 21;
                                        const pvp = parseFloat(this.$refs.pvp.value) || 0;
                                        const net = g * (1 - d / 100);
                                        if (pvp > 0) {
                                            const noVat = pvp / (1 + vat / 100);
                                            const mrg = net > 0 ? (1 - net / noVat) * 100 : 100;
                                            const ben = noVat - net;
                                            this.benefit = ben.toFixed(2);
                                            this.$refs.margin.value = mrg.toFixed(2);
                                            $wire.set('items.{{ $index }}.margin', parseFloat(mrg.toFixed(2)));
                                            $wire.set('items.{{ $index }}.benefit', parseFloat(ben.toFixed(2)));
                                            $wire.set('items.{{ $index }}.vat_amount', parseFloat((noVat * vat / 100).toFixed(2)));
                                        }
                                    }
                                }">
                                {{-- Referencia --}}
                                <td class="px-1 py-0.5">
                                    <input type="text" wire:model="items.{{ $index }}.reference"
                                        onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Descripción --}}
                                <td class="px-1 py-0.5">
                                    <input type="text" wire:model="items.{{ $index }}.description"
                                        onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Cantidad --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.01"
                                        wire:model="items.{{ $index }}.quantity" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Precio compra --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.0001"
                                        wire:model="items.{{ $index }}.unit_price" x-ref="gross"
                                        @input="updateFromMargin()" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Dto% --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.01"
                                        wire:model="items.{{ $index }}.discount" x-ref="dto"
                                        @input="updateFromMargin()" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="0" {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Margen% --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.01"
                                        wire:model="items.{{ $index }}.margin" x-ref="margin"
                                        @input="updateFromMargin()" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="60" {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Beneficio (calculado) --}}
                                <td class="px-1 py-0.5 text-right font-medium text-gray-700 dark:text-gray-300">
                                    <span x-text="parseFloat(benefit).toFixed(2)"></span> €
                                </td>
                                {{-- IVA% --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.01"
                                        wire:model="items.{{ $index }}.vat_rate" x-ref="vat"
                                        @input="updateFromMargin()" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="21" {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- PVP Psicológico --}}
                                <td class="px-1 py-0.5">
                                    <input type="number" step="0.01"
                                        wire:model="items.{{ $index }}.sale_price" x-ref="pvp"
                                        @input="updateFromPvp()" onfocus="this.select()"
                                        class="block w-full text-xs rounded border-gray-300 text-right font-semibold dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        {{ $status !== 'pending' ? 'disabled' : '' }}>
                                </td>
                                {{-- Total línea --}}
                                <td class="px-1 py-0.5 text-right font-medium text-gray-700 dark:text-gray-300">
                                    {{ number_format($subtotal, 2) }} €
                                </td>
                                {{-- Indicador de producto existente --}}
                                <td class="px-1 py-0.5 text-center">
                                    @if ($isExisting)
                                        <button type="button"
                                            wire:click="showProductComparison({{ $index }})"
                                            title="Producto ya existe en BD — pulsa para ver datos actuales"
                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200 transition-colors">
                                            ⚠️ Existente
                                        </button>
                                    @else
                                        <span
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700 border border-green-200">
                                            ✨ Nuevo
                                        </span>
                                    @endif
                                </td>
                                @if ($status === 'pending')
                                    <td class="px-1 py-0.5 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $status === 'pending' ? 12 : 11 }}"
                                    class="text-center py-6 text-gray-400 text-xs italic">
                                    No hay líneas. Pulsa "+ Añadir Línea" para añadir manualmente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── OCR Raw ────────────────────────────────────────────────── --}}
        @if ($raw_text)
            <details class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-xs text-gray-500 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-gray-600 dark:text-gray-300">Texto OCR original
                </summary>
                <pre class="mt-2 whitespace-pre-wrap break-words font-mono text-[10px]">{{ $raw_text }}</pre>
            </details>
        @endif

        {{-- ── Botones inferiores ─────────────────────────────────────── --}}
        @if ($status === 'pending')
            <div class="flex justify-end gap-2 pt-2">
                <x-filament::button color="gray" size="sm" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>💾 Guardar cambios</span>
                    <span wire:loading>Guardando...</span>
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
