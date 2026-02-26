<?php

namespace App\Filament\Widgets;

use App\Models\DocumentoLinea;
use App\Models\TicketItem;
use App\Models\Product;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Productos por Ingresos (Últimos 30 días)';
    protected static ?int $sort = 4;
    protected static ?string $maxHeight = '400px';

    protected function getData(): array
    {
        $startDate = now()->subDays(30);

        $docSales = DB::table('documento_lineas')
            ->join('documentos', 'documentos.id', '=', 'documento_lineas.documento_id')
            ->where('documentos.tipo', 'factura')
            ->whereNotIn('documentos.estado', ['borrador', 'anulado'])
            ->where('documentos.fecha', '>=', $startDate)
            ->select('product_id', DB::raw('SUM(documento_lineas.subtotal) as total_revenue'))
            ->groupBy('product_id');

        $ticketSales = DB::table('ticket_items')
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->where('tickets.status', 'completed')
            ->where('tickets.completed_at', '>=', $startDate)
            ->select('product_id', DB::raw('SUM(ticket_items.subtotal) as total_revenue'))
            ->groupBy('product_id');

        $topProducts = DB::table(DB::raw("({$docSales->toSql()} UNION ALL {$ticketSales->toSql()}) as combined_sales"))
            ->mergeBindings($docSales)
            ->mergeBindings($ticketSales)
            ->select('product_id', DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('product_id')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($topProducts as $item) {
            $product = Product::find($item->product_id);
            $labels[] = $product ? $product->name : "Producto #{$item->product_id}";
            $data[] = (float)$item->revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (€)',
                    'data' => $data,
                    'backgroundColor' => [
                        '#fbbf24', '#f59e0b', '#d97706', '#b45309', '#92400e',
                        '#78350f', '#451a03', '#ea580c', '#c2410c', '#9a3412'
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
            'maintainAspectRatio' => false,
        ];
    }
}
