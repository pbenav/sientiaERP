<?php

namespace App\Filament\Resources\LabelFormatResource\Pages;

use App\Filament\Resources\LabelFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLabelFormat extends EditRecord
{
    protected static string $resource = LabelFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
