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
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->record->confirmar();
                        $this->refreshFormData(['estado', 'numero']);
                        \Filament\Notifications\Notification::make()->title('Pedido confirmado')->success()->send();
                    } catch (\Exception $e) {
                         \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('convertir_albaran')
                ->label('Generar Albarán')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn() => in_array($this->record->estado, ['confirmado', 'parcial']))
                ->requiresConfirmation()
                ->action(function () {
                    $albaran = $this->record->convertirA('albaran');
                    \Filament\Notifications\Notification::make()->title('Albarán creado')->success()->send();
                    return redirect()->route('filament.admin.resources.albaranes.edit', $albaran);
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

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        parent::save($shouldRedirect, $shouldSendSavedNotification);
        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


}
