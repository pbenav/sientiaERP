<?php

namespace App\Filament\Resources\ReciboCompraResource\Pages;

use App\Filament\Resources\ReciboCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReciboCompra extends EditRecord
{
    protected static string $resource = ReciboCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalcularTotales();
    }
}
