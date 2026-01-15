<?php

namespace App\Filament\Resources\FacturaCompraResource\Pages;

use App\Filament\Resources\FacturaCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacturaCompra extends CreateRecord
{
    protected static string $resource = FacturaCompraResource::class;

    public function mount(): void
    {
        $data = [
            'tipo' => 'factura_compra',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
