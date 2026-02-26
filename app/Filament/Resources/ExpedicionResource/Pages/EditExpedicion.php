<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use App\Filament\Resources\ExpedicionResource\Widgets\ExpedicionTotalesWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpedicion extends EditRecord
{
    protected static string $resource = ExpedicionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExpedicionTotalesWidget::make(['record' => (string) $this->record->id]),
        ];
    }
}
