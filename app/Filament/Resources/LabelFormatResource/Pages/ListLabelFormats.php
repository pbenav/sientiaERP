<?php

namespace App\Filament\Resources\LabelFormatResource\Pages;

use App\Filament\Resources\LabelFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabelFormats extends ListRecords
{
    protected static string $resource = LabelFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
