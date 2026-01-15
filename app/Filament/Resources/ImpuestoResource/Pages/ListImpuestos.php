<?php

namespace App\Filament\Resources\ImpuestoResource\Pages;

use App\Filament\Resources\ImpuestoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImpuestos extends ListRecords
{
    protected static string $resource = ImpuestoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
