<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('openInPOS')
                ->label('Abrir en POS')
                ->icon('heroicon-o-computer-desktop')
                ->url(fn () => $this->getResource()::getUrl('create', ['ticket_id' => $this->record->id]))
                ->color('primary')
                ->visible(fn () => $this->record->status === 'open'),
            
            Actions\Action::make('send_verifactu')
                ->label('Enviar Veri*Factu')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => auth()->user()->isSuperAdmin() && \App\Models\Setting::get('verifactu_active', false) && $this->record->status === 'completed' && $this->record->verifactu_status !== 'accepted')
                ->action(function () {
                    $verifactuService = app(\App\Services\VerifactuService::class);
                    $res = $verifactuService->enviarAEAT($this->record);
                    if ($res['success']) {
                        \Filament\Notifications\Notification::make()->title('Veri*Factu: Aceptado')->success()->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Veri*Factu: Error')
                            ->body($res['error'] ?? 'Error desconocido')
                            ->danger()
                            ->persistent()
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
        ];
    }
}
