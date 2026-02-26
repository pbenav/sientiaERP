<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class MonthlySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Ventas del Mes (POS + Gestión)';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // POS Cash
        $posCash = Ticket::where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->sum('pago_efectivo');

        // POS Card
        $posCard = Ticket::where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->sum('pago_tarjeta');

        // Paid Receipts (Sales)
        $receipts = Documento::where('tipo', 'recibo')
            ->where('estado', 'cobrado')
            ->whereBetween('fecha_vencimiento', [$startOfMonth, $endOfMonth])
            ->sum('total');

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (€)',
                    'data' => [
                        (float) $posCash,
                        (float) $posCard,
                        (float) $receipts,
                    ],
                    'backgroundColor' => [
                        '#10b981', // Green (Cash)
                        '#3b82f6', // Blue (Card)
                        '#f59e0b', // Amber (Receipts)
                    ],
                ],
            ],
            'labels' => ['POS Contado', 'POS Tarjeta', 'Recibos Cobrados'],
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
            'maintainAspectRatio' => false,
        ];
    }
}
