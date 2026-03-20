<?php

namespace App\Filament\Resources\FacturaCompraResource\Pages;

use App\Filament\Resources\FacturaCompraResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFacturaCompra extends EditRecord
{
    protected static string $resource = FacturaCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->requiresConfirmation()
                ->modalHeading('Confirmar Factura de Compra')
                ->modalDescription('Al confirmar la factura se asignará la fecha de hoy (' . now()->format('d/m/Y') . ') para garantizar correlación numérica. ¿Desea continuar?')
                ->action(function () {
                    try {
                        $this->record->fecha = now();
                        $this->record->confirmar();
                        $this->refreshFormData(['estado', 'numero', 'fecha']);
                        Notification::make()->title('Factura confirmada')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('generar_recibos')
                ->label('Generar Recibos')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(function () {
                    return $this->record->estado === 'confirmado' && 
                           !\App\Models\Documento::where('documento_origen_id', $this->record->id)->where('tipo', 'recibo_compra')->exists();
                })
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $service = new \App\Services\RecibosService();
                        $recibos = $service->generarRecibosDesdeFactura($this->record);
                        Notification::make()->title('Recibos generados')->success()->body("Se han generado {$recibos->count()} recibo(s)")->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('anular')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->estado === 'confirmado' && !empty($this->record->numero))
                ->action(function () {
                    try {
                        $this->record->anular();
                        $this->refreshFormData(['estado']);
                        Notification::make()->title('Factura anulada')->danger()->send();
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

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        parent::save($shouldRedirect, $shouldSendSavedNotification);
        $this->redirect($this->getResource()::getUrl('index'));
    }



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->recalcularTotales();
    }




}
