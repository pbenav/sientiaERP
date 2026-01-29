<div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
            Desglose de Totales (Estimado)
        </h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">Base Imponible</th>
                    <th scope="col" class="px-6 py-3 text-center">% IVA</th>
                    <th scope="col" class="px-6 py-3 text-right">Cuota IVA</th>
                    @if($tieneRecargo)
                        <th scope="col" class="px-6 py-3 text-center">% RE</th>
                        <th scope="col" class="px-6 py-3 text-right">Cuota RE</th>
                    @endif
                    <th scope="col" class="px-6 py-3 text-right">Total</th>
                </tr>
            </thead>
                <tbody>
                    @foreach($breakdown['impuestos'] as $impuesto)
                        <tr class="border-b bg-white dark:border-gray-700 dark:bg-gray-800">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ \App\Helpers\NumberFormatHelper::formatCurrency($impuesto['base']) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                {{ number_format($impuesto['iva'], 0) }}%
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{ \App\Helpers\NumberFormatHelper::formatCurrency($impuesto['cuota_iva']) }}
                            </td>
                            @if($tieneRecargo)
                                <td class="px-6 py-4 text-center">
                                    {{ number_format($impuesto['re'], 2) }}%
                                </td>
                                <td class="px-6 py-4 text-right">
                                    {{ \App\Helpers\NumberFormatHelper::formatCurrency($impuesto['cuota_re']) }}
                                </td>
                            @endif
                            <td class="px-6 py-4 text-right font-bold">
                                {{ \App\Helpers\NumberFormatHelper::formatCurrency($impuesto['total']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-900 dark:bg-gray-700 dark:text-white">
                   <tr>
                        <td class="px-6 py-3 text-right" colspan="{{ $tieneRecargo ? 5 : 3 }}">
                            TOTAL DOCUMENTO
                        </td>
                        <td class="px-6 py-3 text-right text-lg text-primary-600">
                            {{ \App\Helpers\NumberFormatHelper::formatCurrency($breakdown['total_documento']) }}
                        </td>
                   </tr>
                </tfoot>
        </table>
    </div>
</div>
