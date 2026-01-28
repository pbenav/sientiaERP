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
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (Documento $record): string => route('etiquetas.pdf', ['record' => $record->id]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
