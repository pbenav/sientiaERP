<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpedicion extends EditRecord
{
    protected static string $resource = ExpedicionResource::class;

    // Después de guardar cambios → vuelve al listado
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
