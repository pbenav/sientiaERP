<?php

namespace App\Filament\Resources\EtiquetaResource\Pages;

use App\Filament\Resources\EtiquetaResource;
use App\Models\Documento;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEtiqueta extends EditRecord
{
    protected static string $resource = EtiquetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('Imprimir etiquetas')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function (Documento $record, EditEtiqueta $livewire) {
                    $livewire->save(shouldRedirect: false);
                    return response()->streamDownload(function () use ($record) {
                        echo app(\App\Services\LabelGeneratorService::class)->generatePDF($record)->output();
                    }, 'etiquetas-' . ($record->numero ?? $record->id) . '.pdf');
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
