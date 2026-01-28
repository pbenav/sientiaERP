<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->action(function () {
                    $this->record->confirmar();
                    $this->refreshFormData(['estado', 'numero']);
                }),
            
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
