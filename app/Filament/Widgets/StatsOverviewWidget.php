<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Ticket;
use App\Helpers\NumberFormatHelper;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        
        $prevStartDate = Carbon::now()->subMonth()->startOfMonth();
        $prevEndDate = Carbon::now()->subMonth()->endOfMonth();

        // Sales (Invoices + Tickets)
        $currentSales = Documento::where('tipo', 'factura')
            ->whereBetween('fecha', [$startDate, $endDate])
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->sum('total') +
            Ticket::whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('total');

        $prevSales = Documento::where('tipo', 'factura')
            ->whereBetween('fecha', [$prevStartDate, $prevEndDate])
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->sum('total') +
            Ticket::whereBetween('completed_at', [$prevStartDate, $prevEndDate])
            ->where('status', 'completed')
            ->sum('total');

        // Purchases (Delivery Notes)
        $currentPurchases = Documento::where('tipo', 'albaran_compra')
            ->whereBetween('fecha', [$startDate, $endDate])
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->sum('total');

        $prevPurchases = Documento::where('tipo', 'albaran_compra')
            ->whereBetween('fecha', [$prevStartDate, $prevEndDate])
            ->whereNotIn('estado', ['borrador', 'anulado'])
            ->sum('total');

        return [
            Stat::make('Ventas del Mes', NumberFormatHelper::formatCurrency($currentSales))
                ->description($this->getDiffDescription($currentSales, $prevSales))
                ->descriptionIcon($this->getDiffIcon($currentSales, $prevSales))
                ->color($this->getDiffColor($currentSales, $prevSales)),
                
            Stat::make('Compras del Mes', NumberFormatHelper::formatCurrency($currentPurchases))
                ->description($this->getDiffDescription($currentPurchases, $prevPurchases, true))
                ->descriptionIcon($this->getDiffIcon($currentPurchases, $prevPurchases, true))
                ->color($this->getDiffColor($currentPurchases, $prevPurchases, true)),

            Stat::make('Margen Neto Estimado', NumberFormatHelper::formatCurrency($currentSales - $currentPurchases))
                ->description('Balance mensual')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),
        ];
    }

    protected function getDiffDescription($current, $prev, $inverse = false): string
    {
        if ($prev == 0) return 'Sin datos previos';
        $diff = (($current - $prev) / $prev) * 100;
        $prefix = $diff >= 0 ? '+' : '';
        return $prefix . number_format($diff, 1) . '% respecto al mes anterior';
    }

    protected function getDiffIcon($current, $prev, $inverse = false): string
    {
        return $current >= $prev ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    protected function getDiffColor($current, $prev, $inverse = false): string
    {
        if ($current == $prev) return 'gray';
        $isBetter = $current > $prev;
        if ($inverse) $isBetter = !$isBetter;
        return $isBetter ? 'success' : 'danger';
    }
}
