<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePedido extends CreateRecord
{
    protected static string $resource = PedidoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'pedido';
        $data['user_id'] = auth()->id();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalcularTotales();
    }
}
