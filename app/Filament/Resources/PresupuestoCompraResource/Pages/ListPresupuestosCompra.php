<?php

namespace App\Filament\Resources\PresupuestoCompraResource\Pages;

use App\Filament\Resources\PresupuestoCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPresupuestosCompra extends ListRecords
{
    protected static string $resource = PresupuestoCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
