<?php

namespace App\Filament\Resources\ReciboCompraResource\Pages;

use App\Filament\Resources\ReciboCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReciboCompra extends CreateRecord
{
    protected static string $resource = ReciboCompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'recibo_compra';
        $data['user_id'] = auth()->id();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalcularTotales();
    }
}
