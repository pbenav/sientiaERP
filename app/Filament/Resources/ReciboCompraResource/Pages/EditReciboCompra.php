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
