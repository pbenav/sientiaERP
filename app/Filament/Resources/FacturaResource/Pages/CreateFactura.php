<?php

namespace App\Filament\Resources\FacturaResource\Pages;

use App\Filament\Resources\FacturaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFactura extends CreateRecord
{
    protected static string $resource = FacturaResource::class;

    public function mount(): void
    {
        $data = [
            'tipo' => 'factura',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
