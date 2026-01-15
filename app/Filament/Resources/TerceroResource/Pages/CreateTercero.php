<?php

namespace App\Filament\Resources\TerceroResource\Pages;

use App\Filament\Resources\TerceroResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTercero extends CreateRecord
{
    protected static string $resource = TerceroResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
