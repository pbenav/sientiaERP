<?php

namespace App\Filament\Widgets;

use App\Models\DocumentoLinea;
use App\Models\Product;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopPurchasedProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Productos Comprados (Últimos 30 días)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $startDate = now()->subDays(30);

        $topProducts = DB::table('documento_lineas')
            ->join('documentos', 'documentos.id', '=', 'documento_lineas.documento_id')
            ->where('documentos.tipo', 'factura_compra')
            ->whereNotIn('documentos.estado', ['borrador', 'anulado'])
            ->where('documentos.fecha', '>=', $startDate)
            ->select('product_id', DB::raw('SUM(documento_lineas.subtotal) as total_cost'))
            ->groupBy('product_id')
            ->orderBy('total_cost', 'desc')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($topProducts as $item) {
            $product = Product::find($item->product_id);
            $labels[] = $product ? $product->name : "Producto #{$item->product_id}";
            $data[] = (float)$item->total_cost;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Coste Total (€)',
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
        return 'pie';
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
