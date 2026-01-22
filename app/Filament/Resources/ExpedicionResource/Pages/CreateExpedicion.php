<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpedicion extends CreateRecord
{
    protected static string $resource = ExpedicionResource::class;

    protected function getRedirectUrl(): string
    {
        // Tras crear, redirige a la edición para poder añadir compras
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
