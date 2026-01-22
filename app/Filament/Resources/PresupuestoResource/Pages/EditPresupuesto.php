<?php

namespace App\Filament\Resources\PresupuestoResource\Pages;

use App\Filament\Resources\PresupuestoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPresupuesto extends EditRecord
{
    protected static string $resource = PresupuestoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->requiresConfirmation()
                ->action(function () {
                     $this->record->confirmar();
                     return redirect()->to($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            
            Actions\Action::make('convertir_pedido')
                ->label('Convertir a Pedido')
                ->icon('heroicon-o-arrow-right')
                ->color('primary')
                ->visible(fn() => $this->record->estado === 'confirmado')
                ->action(function () {
                    $pedido = $this->record->convertirA('pedido');
                    return redirect()->route('filament.admin.resources.pedidos.edit', $pedido);
                }),
            
            Actions\DeleteAction::make(),
        ];
    }

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function afterSave(): void
    {
        // Recalcular totales después de guardar
        $this->record->refresh();
        $this->record->recalcularTotales();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
