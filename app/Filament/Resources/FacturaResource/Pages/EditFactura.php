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
                ->modalDescription('Al confirmar la factura se asignará la fecha de hoy (' . now()->format('d/m/Y') . ') para garantizar la correlación numérica de la serie. ¿Desea continuar?')
                ->action(function () {
                    try {
                        // Guardar cambios del formulario primero (importante para capturar el cliente/tercero_id)
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        
                        $this->record->fecha = now();
                        $this->record->confirmar();
                        $this->refreshFormData(['numero', 'estado', 'fecha']);
                        
                        Notification::make()
                            ->title('Factura confirmada')
                            ->body("Se ha asignado el número {$this->record->numero}")
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        // Errores de validación del formulario de Filament
                        throw $e;
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
                        // Guardar cambios primero
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        
                        $this->record->fecha = now();
                        $this->record->numero = \App\Models\NumeracionDocumento::generarNumero(
                            $this->record->tipo,
                            $this->record->serie ?? 'A'
                        );
                        $this->record->save();
                        $this->refreshFormData(['numero', 'fecha']);
                        
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

            Actions\Action::make('send_verifactu')
                ->label('Enviar Veri*Factu')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => auth()->user()->isSuperAdmin() && \App\Models\Setting::get('verifactu_active', false) && $this->record->estado === 'confirmado' && $this->record->verifactu_status !== 'accepted')
                ->action(function () {
                    $verifactuService = app(\App\Services\VerifactuService::class);
                    $res = $verifactuService->enviarAEAT($this->record);
                    if ($res['success']) {
                        Notification::make()->title('Veri*Factu: Aceptado')->success()->send();
                        $this->refreshFormData(['verifactu_status', 'verifactu_aeat_id', 'verifactu_huella']);
                    } else {
                        Notification::make()
                            ->title('Veri*Factu: Error')
                            ->body($res['error'] ?? 'Error desconocido')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('send_face')
                ->label('Enviar FACe')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Enviar a FACe')
                ->modalDescription('¿Está seguro de que desea enviar esta factura directamente al portal FACe de la Administración Pública?')
                ->visible(fn() => \App\Models\Setting::get('facturae_active', false) && $this->record->estado === 'confirmado' && empty($this->record->facturae_face_id))
                ->action(function () {
                    $tercero = $this->record->tercero;
                    if (empty($tercero->dir3_oficina_contable) || empty($tercero->dir3_organo_gestor) || empty($tercero->dir3_unidad_tramitadora)) {
                        Notification::make()
                            ->title('Faltan códigos DIR3')
                            ->warning()
                            ->body('El cliente no tiene configurados los códigos DIR3. Por favor, rellénelos en la ficha del cliente antes de enviar a FACe.')
                            ->persistent()
                            ->send();
                        return;
                    }

                    $service = app(\App\Services\FaceService::class);
                    $result = $service->enviarFactura($this->record);
                    
                    if ($result['success']) {
                        Notification::make()
                            ->title('Factura enviada a FACe')
                            ->success()
                            ->body($result['message'])
                            ->send();
                        $this->refreshFormData(['facturae_face_id', 'facturae_status']);
                    } else {
                        $this->record->update([
                            'facturae_last_error' => $result['error'],
                            'facturae_last_response' => $result['raw_body'] ?? null
                        ]);
                        $this->refreshFormData(['facturae_last_error', 'facturae_last_response']);

                        Notification::make()
                            ->title('Error en envío a FACe')
                            ->danger()
                            ->body("La respuesta de RedSARA no fue válida. Revise el rastro técnico.")
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('check_face_status')
                ->label('Verificar FACe')
                ->icon('heroicon-m-magnifying-glass')
                ->color('info')
                ->visible(fn() => !empty($this->record->facturae_face_id))
                ->action(function () {
                    $service = app(\App\Services\FaceService::class);
                    $res = $service->consultarFactura($this->record->facturae_face_id);
                    
                    if ($res['success']) {
                        $this->record->update(['facturae_status' => $res['codigo_estado']]);
                        Notification::make()
                            ->title('Estado en FACe')
                            ->body("Estado: {$res['estado']} ({$res['codigo_estado']})")
                            ->info()
                            ->send();
                        $this->refreshFormData(['facturae_status']);
                    } else {
                        Notification::make()
                            ->title('Error al consultar FACe')
                            ->body($res['error'])
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('verify_aeat')
                ->label('Verificar QR')
                ->icon('heroicon-m-qr-code')
                ->color('info')
                ->visible(fn() => $this->record->verifactu_status === 'accepted')
                ->url(fn() => $this->record->verifactu_qr_url)
                ->openUrlInNewTab(),

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
