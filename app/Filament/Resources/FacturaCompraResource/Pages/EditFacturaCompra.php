<?php

namespace App\Filament\Resources\FacturaCompraResource\Pages;

use App\Filament\Resources\FacturaCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFacturaCompra extends EditRecord
{
    protected static string $resource = FacturaCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->recalcularTotales();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->successRedirectUrl($this->getRedirectUrl());
    }
}
