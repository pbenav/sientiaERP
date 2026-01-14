<?php

namespace App\Filament\Resources\AlbaranCompraResource\Pages;

use App\Filament\Resources\AlbaranCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlbaranesCompra extends ListRecords
{
    protected static string $resource = AlbaranCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
