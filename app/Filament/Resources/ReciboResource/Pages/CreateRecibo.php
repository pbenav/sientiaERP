<?php

namespace App\Filament\Resources\ReciboResource\Pages;

use App\Filament\Resources\ReciboResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecibo extends CreateRecord
{
    protected static string $resource = ReciboResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'recibo';
        $data['user_id'] = auth()->id();
        
        return $data;
    }
}
