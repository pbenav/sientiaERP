<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class DailySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Ventas del Día (POS + Gestión)';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $today = Carbon::today();

        // POS Cash
        $posCash = Ticket::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('pago_efectivo');

        // POS Card
        $posCard = Ticket::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('pago_tarjeta');

        // Paid Receipts (Sales)
        // We use fecha_vencimiento as it stores the payment date in RecibosService::marcarComoCobrado
        $receipts = Documento::where('tipo', 'recibo')
            ->where('estado', 'cobrado')
            ->whereDate('fecha_vencimiento', $today)
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
}
