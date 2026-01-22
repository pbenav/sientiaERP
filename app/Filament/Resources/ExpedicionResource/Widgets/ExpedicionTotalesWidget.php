<?php

namespace App\Filament\Resources\ExpedicionResource\Widgets;

use App\Models\Expedicion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ExpedicionTotalesWidget extends StatsOverviewWidget
{
    // Filament inyecta el record de la página automáticamente
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        /** @var Expedicion $expedicion */
        $expedicion = $this->record instanceof Expedicion
            ? $this->record
            : Expedicion::find($this->record);

        if (!$expedicion) {
            return [];
        }

        $total      = $expedicion->compras()->sum('importe');
        $sinPagar   = $expedicion->compras()->where('pagado', false)->count();
        $sinRecoger = $expedicion->compras()->where('pagado', true)->where('recogido', false)->count();

        return [
            Stat::make('💶 Total compras', number_format($total, 2, ',', '.') . ' €')
                ->color('primary'),

            Stat::make('💳 Sin pagar', $sinPagar . ' compra(s)')
                ->color($sinPagar > 0 ? 'danger' : 'success'),

            Stat::make('🚚 Pagado, sin recoger', $sinRecoger . ' compra(s)')
                ->color($sinRecoger > 0 ? 'warning' : 'success'),
        ];
    }
}
