<?php

namespace App\Filament\Resources\PresupuestoResource\Pages;

use App\Filament\Resources\PresupuestoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePresupuesto extends CreateRecord
{
    protected static string $resource = PresupuestoResource::class;

    public function mount(): void
    {
        $data = [
            'tipo' => 'presupuesto',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
            'fecha_validez' => now()->addDays(30),
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
