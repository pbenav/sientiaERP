<?php

namespace App\Filament\Resources\AlbaranResource\Pages;

use App\Filament\Resources\AlbaranResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlbaran extends CreateRecord
{
    protected static string $resource = AlbaranResource::class;

    public function mount(): void
    {
        $data = [
            'tipo' => 'albaran',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
