<?php

namespace App\Filament\Resources\TerceroResource\Pages;

use App\Filament\Resources\TerceroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTercero extends EditRecord
{
    protected static string $resource = TerceroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
