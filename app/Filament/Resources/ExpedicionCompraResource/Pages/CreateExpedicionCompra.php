<?php

namespace App\Filament\Resources\ExpedicionCompraResource\Pages;

use App\Filament\Resources\ExpedicionCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpedicionCompra extends CreateRecord
{
    protected static string $resource = ExpedicionCompraResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
