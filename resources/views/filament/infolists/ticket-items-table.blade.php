<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Producto</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Cantidad</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Precio Unit.</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($getRecord()->items as $item)
                <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                        {{ $item->product->name ?? 'Producto eliminado' }}
                    </td>
                    <td class="px-4 py-3 text-center text-gray-900 dark:text-gray-100">
                        {{ $item->quantity }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                        {{ number_format($item->unit_price, 2, ',', '.') }} €
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($item->total, 2, ',', '.') }} €
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                        No hay productos en este ticket
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
