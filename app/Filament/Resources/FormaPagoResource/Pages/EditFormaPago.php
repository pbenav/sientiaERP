<?php

namespace App\Filament\Resources\FormaPagoResource\Pages;

use App\Filament\Resources\FormaPagoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormaPago extends EditRecord
{
    protected static string $resource = FormaPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
