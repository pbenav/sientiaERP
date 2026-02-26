<?php

namespace App\Filament\Resources\ExpedicionCompraResource\Pages;

use App\Filament\Resources\ExpedicionCompraResource;
use App\Filament\Resources\ExpedicionCompraResource\Widgets\ExpedicionStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListExpedicionesCompra extends ListRecords
{
    protected static string $resource = ExpedicionCompraResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ExpedicionStatsWidget::class,
        ];
    }
}
