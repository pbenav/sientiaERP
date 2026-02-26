<?php

namespace App\Filament\Resources\CashSessionResource\Pages;

use App\Filament\Resources\CashSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCashSession extends CreateRecord
{
    protected static string $resource = CashSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['fecha_inicio'] = now();
        $data['estado'] = 'open';

        return $data;
    }
}
