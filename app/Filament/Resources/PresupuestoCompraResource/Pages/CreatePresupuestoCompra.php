<?php

namespace App\Filament\Resources\PresupuestoCompraResource\Pages;

use App\Filament\Resources\PresupuestoCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePresupuestoCompra extends CreateRecord
{
    protected static string $resource = PresupuestoCompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'presupuesto_compra';
        $data['user_id'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
