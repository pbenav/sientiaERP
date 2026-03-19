<?php

namespace App\Filament\Resources\PresupuestoCompraResource\Pages;

use App\Filament\Resources\PresupuestoCompraResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPresupuestoCompra extends EditRecord
{
    protected static string $resource = PresupuestoCompraResource::class;

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
                     try {
                        $this->record->confirmar();
                        $this->refreshFormData(['estado', 'numero']);
                        Notification::make()->title('Presupuesto confirmado')->success()->send();
                     } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                     }
                }),
            
            Actions\Action::make('convertir_pedido')
                ->label('Convertir a Pedido')
                ->icon('heroicon-o-arrow-right')
                ->color('primary')
                ->visible(fn() => $this->record->estado === 'confirmado')
                ->action(function () {
                    try {
                        $pedido = $this->record->convertirA('pedido_compra');
                        Notification::make()->title('Pedido de compra creado')->success()->send();
                        return redirect()->route('filament.admin.resources.pedido-compras.edit', $pedido);
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),
            
            Actions\Action::make('pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn() => route('documentos.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('ticket')
                ->label('Imprimir Ticket')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn() => route('documentos.ticket', $this->record))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->puedeEliminarse()),
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
}
