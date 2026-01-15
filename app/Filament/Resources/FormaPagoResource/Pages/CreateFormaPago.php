<?php

namespace App\Filament\Resources\FormaPagoResource\Pages;

use App\Filament\Resources\FormaPagoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFormaPago extends CreateRecord
{
    protected static string $resource = FormaPagoResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
