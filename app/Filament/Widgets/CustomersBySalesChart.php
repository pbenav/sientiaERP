<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Ticket;
use App\Models\Tercero;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomersBySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Ventas por Cliente (Top 10)';
    protected static ?int $sort = 5;
    protected static ?string $maxHeight = '400px';

    protected function getData(): array
    {
        $startDate = now()->subDays(90);

        $docSales = DB::table('documentos')
            ->where('tipo', 'factura')
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->where('fecha', '>=', $startDate)
            ->select('tercero_id', DB::raw('SUM(total) as total_revenue'))
            ->groupBy('tercero_id');

        $ticketSales = DB::table('tickets')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $startDate)
            ->select('tercero_id', DB::raw('SUM(total) as total_revenue'))
            ->groupBy('tercero_id');

        $topCustomers = DB::table(DB::raw("({$docSales->toSql()} UNION ALL {$ticketSales->toSql()}) as combined_sales"))
            ->mergeBindings($docSales)
            ->mergeBindings($ticketSales)
            ->select('tercero_id', DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('tercero_id')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($topCustomers as $item) {
            $customer = Tercero::find($item->tercero_id);
            $labels[] = $customer ? $customer->nombre_comercial : "Cliente #{$item->tercero_id}";
            $data[] = (float)$item->revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (€)',
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
