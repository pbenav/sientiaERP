<?php

namespace App\Filament\Resources\ReciboCompraResource\Pages;

use App\Filament\Resources\ReciboCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecibosCompra extends ListRecords
{
    protected static string $resource = ReciboCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
