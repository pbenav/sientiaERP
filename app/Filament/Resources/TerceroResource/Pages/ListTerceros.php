<?php

namespace App\Filament\Resources\TerceroResource\Pages;

use App\Filament\Resources\TerceroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTerceros extends ListRecords
{
    protected static string $resource = TerceroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
