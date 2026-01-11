<?php

namespace App\Filament\Resources\PresupuestoResource\Pages;

use App\Filament\Resources\PresupuestoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePresupuesto extends CreateRecord
{
    protected static string $resource = PresupuestoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'presupuesto';
        $data['user_id'] = auth()->id();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalcular totales después de crear
        $this->record->recalcularTotales();
    }
}
