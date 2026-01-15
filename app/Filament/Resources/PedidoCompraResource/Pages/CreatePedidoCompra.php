<?php

namespace App\Filament\Resources\PedidoCompraResource\Pages;

use App\Filament\Resources\PedidoCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePedidoCompra extends CreateRecord
{
    protected static string $resource = PedidoCompraResource::class;

    public function mount(): void
    {
        $data = [
            'tipo' => 'pedido_compra',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
