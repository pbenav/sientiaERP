<?php

namespace App\Filament\Resources\FormaPagoResource\Pages;

use App\Filament\Resources\FormaPagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormaPagos extends ListRecords
{
    protected static string $resource = FormaPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
