<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Ventas vs Compras (Últimos 12 meses)';
    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $months = collect(range(0, 11))->map(function ($i) {
            return Carbon::now()->subMonths($i)->format('Y-m');
        })->reverse()->values();

        $salesData = [];
        $purchasesData = [];

        foreach ($months as $month) {
            [$year, $m] = explode('-', $month);
            
            $monthSales = Documento::where('tipo', 'factura')
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $m)
                ->whereNotIn('estado', ['borrador', 'anulado'])
                ->sum('total') +
                Ticket::whereYear('completed_at', $year)
                ->whereMonth('completed_at', $m)
                ->where('status', 'completed')
                ->sum('total');

            $monthPurchases = Documento::where(function($query) {
                    $query->where('tipo', 'albaran_compra')->where('estado', 'confirmado');
                })
                ->orWhere(function($query) {
                    $query->where('tipo', 'factura_compra')->whereNotIn('estado', ['borrador', 'anulado']);
                })
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $m)
                ->sum('total');

            $salesData[] = (float)$monthSales;
            $purchasesData[] = (float)$monthPurchases;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas',
                    'data' => $salesData,
                    'borderColor' => '#fbbf24',
                    'backgroundColor' => 'rgba(251, 191, 36, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Compras',
                    'data' => $purchasesData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn($m) => Carbon::parse($m)->translatedFormat('M Y'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
