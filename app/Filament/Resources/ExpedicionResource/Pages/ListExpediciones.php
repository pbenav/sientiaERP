<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpediciones extends ListRecords
{
    protected static string $resource = ExpedicionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva expedición'),
        ];
    }
}
