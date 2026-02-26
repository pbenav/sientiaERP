<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Tercero;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SupplierPurchasesChart extends ChartWidget
{
    protected static ?string $heading = 'Compras por Proveedor (Top 10)';
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $startDate = now()->subDays(90);

        $topSuppliers = DB::table('documentos')
            ->where('tipo', 'factura_compra')
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->where('fecha', '>=', $startDate)
            ->select('tercero_id', DB::raw('SUM(total) as total_spent'))
            ->groupBy('tercero_id')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($topSuppliers as $item) {
            $supplier = Tercero::find($item->tercero_id);
            $labels[] = $supplier ? $supplier->nombre_comercial : "Proveedor #{$item->tercero_id}";
            $data[] = (float)$item->total_spent;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Compras (€)',
                    'data' => $data,
                    'backgroundColor' => [
                        '#ef4444', '#dc2626', '#b91c1c', '#991b1b', '#7f1d1d',
                        '#450a0a', '#f87171', '#fca5a5', '#fecaca', '#fee2e2'
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
