<?php

namespace App\Filament\Resources\FacturaCompraResource\Pages;

use App\Filament\Resources\FacturaCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacturasCompra extends ListRecords
{
    protected static string $resource = FacturaCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
