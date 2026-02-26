<?php

namespace App\Filament\Resources\ExpedicionCompraResource\Widgets;

use App\Models\ExpedicionCompra;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpedicionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    // Vinculado al Resource para que solo aparezca en sus páginas
    public static function canView(): bool
    {
        return true;
    }

    protected function getStats(): array
    {
        $total        = ExpedicionCompra::activas()->sum('importe');
        $alertas      = ExpedicionCompra::pendientesRecogida()->count();
        $sinPagar     = ExpedicionCompra::sinPagar()->count();
        $numRegistros = ExpedicionCompra::activas()->count();

        return [
            Stat::make('Total expedición', number_format($total, 2, ',', '.') . ' €')
                ->description($numRegistros . ' registros activos')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('⚠️ Pendientes de recoger', $alertas)
                ->description('Pagado pero aún no recogido')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($alertas > 0 ? 'danger' : 'success'),

            Stat::make('Sin pagar', $sinPagar)
                ->description('Registros pendientes de pago')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($sinPagar > 0 ? 'warning' : 'success'),
        ];
    }
}
