<x-filament-panels::page>
    {{-- Header de la expedición --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Fecha</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $this->record->fecha?->format('d/m/Y') ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Lugar</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $this->record->lugar ?: '—' }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Total compras</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ number_format($this->record->compras->sum('importe'), 2, ',', '.') }} €
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Compras a procesar</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $this->record->compras->count() }} total
                </p>
            </div>
        </div>
    </div>

    {{-- Tabla de compras --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn flex items-center gap-x-3 px-6 py-4 border-b border-gray-100 dark:border-white/10">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Compras de la expedición
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Proveedor</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Importe</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Fecha</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pagado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Recogido</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Documento</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->record->compras as $compra)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            {{-- Proveedor --}}
                            <td class="px-4 py-3 text-gray-900 dark:text-white">
                                {{ $compra->tercero?->nombre_comercial ?? '<em class="text-gray-400">Sin proveedor</em>' }}
                            </td>
                            {{-- Importe --}}
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900 dark:text-white">
                                {{ number_format($compra->importe ?? 0, 2, ',', '.') }} €
                            </td>
                            {{-- Fecha --}}
                            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">
                                {{ $compra->fecha?->format('d/m/Y') ?? '—' }}
                            </td>
                            {{-- Pagado --}}
                            <td class="px-4 py-3 text-center">
                                @if ($compra->pagado)
                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-500/20 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">✓ Sí</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-500/20 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">✗ No</span>
                                @endif
                            </td>
                            {{-- Recogido --}}
                            <td class="px-4 py-3 text-center">
                                @if ($compra->recogido)
                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-500/20 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">✓ Sí</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-500/20 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">✗ No</span>
                                @endif
                            </td>
                            {{-- Documento --}}
                            <td class="px-4 py-3 text-center">
                                @if ($compra->documento_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($compra->documento_path) }}"
                                       target="_blank"
                                       class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-500/20 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400 hover:bg-blue-200 transition-colors">
                                        📄 Ver
                                    </a>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            {{-- Acción OCR --}}
                            <td class="px-4 py-3 text-center">
                                @if ($compra->documento_id)
                                    <a href="{{ route('filament.admin.resources.albaran-compras.edit', ['record' => $compra->documento_id]) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-3 py-1.5 transition-colors shadow-sm">
                                        ✅ Ver Albarán #{{ $compra->documento?->numero ?? $compra->documento_id }}
                                    </a>
                                @else
                                    <a href="{{ $this->getOcrUrl($compra->id) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold px-3 py-1.5 transition-colors shadow-sm">
                                        🤖 Importar IA
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                Esta expedición no tiene compras registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
