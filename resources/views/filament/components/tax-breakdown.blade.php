<div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @php
        $record = isset($record) ? $record : (method_exists($this, 'getRecord') ? $this->getRecord() : null);
    @endphp
    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
            Desglose de Impuestos
        </h3>
    </div>
    
    @if($record)
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Base Imponible</th>
                        <th scope="col" class="px-6 py-3 text-center">% IVA</th>
                        <th scope="col" class="px-6 py-3 text-right">Cuota IVA</th>
                        @if($record->recargo_equivalencia > 0)
                            <th scope="col" class="px-6 py-3 text-center">% RE</th>
                            <th scope="col" class="px-6 py-3 text-right">Cuota RE</th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->getDesgloseImpuestos() as $impuesto)
                        <tr class="border-b bg-white dark:border-gray-700 dark:bg-gray-800">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ number_format($impuesto['base'], 2, ',', '.') }} €
                            </td>
                            <td class="px-6 py-4 text-center">
                                {{ number_format($impuesto['iva'], 0) }}%
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{ number_format($impuesto['cuota_iva'], 2, ',', '.') }} €
                            </td>
                            @if($record->recargo_equivalencia > 0)
                                <td class="px-6 py-4 text-center">
                                    {{ number_format($impuesto['re'], 2) }}%
                                </td>
                                <td class="px-6 py-4 text-right">
                                    {{ number_format($impuesto['cuota_re'], 2, ',', '.') }} €
                                </td>
                            @endif
                            <td class="px-6 py-4 text-right font-bold">
                                {{ number_format($impuesto['total'], 2, ',', '.') }} €
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-900 dark:bg-gray-700 dark:text-white">
                   <tr>
                        <td class="px-6 py-3 text-right" colspan="{{ $record->recargo_equivalencia > 0 ? 5 : 3 }}">
                            TOTAL DOCUMENTO
                        </td>
                        <td class="px-6 py-3 text-right text-base text-primary-600">
                            {{ number_format($record->total, 2, ',', '.') }} €
                        </td>
                   </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="p-6 text-center text-gray-500">
            Guarde el documento para ver el desglose.
        </div>
    @endif
</div>
