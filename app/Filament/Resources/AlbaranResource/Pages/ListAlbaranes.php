<?php

namespace App\Filament\Resources\AlbaranResource\Pages;

use App\Filament\Resources\AlbaranResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlbaranes extends ListRecords
{
    protected static string $resource = AlbaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
