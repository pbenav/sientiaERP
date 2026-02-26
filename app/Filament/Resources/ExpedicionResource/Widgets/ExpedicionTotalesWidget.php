<?php

namespace App\Filament\Resources\ExpedicionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpedicionTotalesWidget extends BaseWidget
{
    // Recibe el record de la expedición actual desde la página de edición
    public ?string $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $expedicion = \App\Models\Expedicion::with('compras')->find($this->record);
        if (! $expedicion) {
            return [];
        }

        $total    = $expedicion->totalImporte();
        $sinRecoger = $expedicion->pendientesRecogida();
        $sinPagar   = $expedicion->sinPagar();

        return [
            Stat::make('Total expedición', number_format($total, 2, ',', '.') . ' €')
                ->icon('heroicon-o-currency-euro')
                ->color('primary'),

            Stat::make('Pendientes de recoger', $sinRecoger)
                ->icon('heroicon-o-truck')
                ->color($sinRecoger > 0 ? 'danger' : 'success')
                ->description($sinRecoger > 0 ? 'Pagado pero sin recoger mercancía' : 'Todo recogido ✓'),

            Stat::make('Sin pagar', $sinPagar)
                ->icon('heroicon-o-banknotes')
                ->color($sinPagar > 0 ? 'warning' : 'success')
                ->description($sinPagar > 0 ? 'Compras pendientes de pago' : 'Todo pagado ✓'),
        ];
    }
}
