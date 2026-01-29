<?php

namespace App\Filament\Resources\FacturaResource\Pages;

use App\Filament\Resources\FacturaResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFactura extends EditRecord
{
    protected static string $resource = FacturaResource::class;



    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->requiresConfirmation()
                ->modalHeading('Confirmar Factura')
                ->modalDescription('Al confirmar la factura se le asignará un número definitivo y ya no podrá ser editada ni eliminada. ¿Desea continuar?')
                ->action(function () {
                    try {
                        $this->record->confirmar();
                        $this->refreshFormData(['numero', 'estado']);
                        
                        Notification::make()
                            ->title('Factura confirmada')
                            ->body("Se ha asignado el número {$this->record->numero}")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al confirmar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            // Botón especial para facturas confirmadas sin número (edge case)
            Actions\Action::make('asignar_numero')
                ->label('Asignar Número')
                ->icon('heroicon-o-hashtag')
                ->color('warning')
                ->visible(fn() => $this->record->estado === 'confirmado' && empty($this->record->numero))
                ->requiresConfirmation()
                ->modalHeading('Asignar Número de Factura')
                ->modalDescription('Esta factura está confirmada pero sin número asignado. Se le asignará un número automático.')
                ->action(function () {
                    try {
                        $this->record->numero = \App\Models\NumeracionDocumento::generarNumero(
                            $this->record->tipo,
                            $this->record->serie ?? 'A'
                        );
                        $this->record->save();
                        $this->refreshFormData(['numero']);
                        
                        Notification::make()
                            ->title('Número asignado')
                            ->body("Se ha asignado el número {$this->record->numero}")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al asignar número')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\Action::make('anular')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Anular Factura')
                ->modalDescription('¿Está seguro de que desea anular esta factura? Esta acción no se puede deshacer y el número de factura quedará invalidado.')
                ->visible(fn() => $this->record->estado === 'confirmado' && !empty($this->record->numero))
                ->action(function () {
                    $this->record->anular();
                    $this->refreshFormData(['estado']);
                    
                    Notification::make()
                        ->title('Factura anulada')
                        ->danger()
                        ->send();
                }),

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
