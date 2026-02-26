<?php

namespace App\Filament\Resources\ExpedicionCompraResource\Pages;

use App\Filament\Resources\ExpedicionCompraResource;
use App\Models\ExpedicionCompra;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

class EditExpedicionCompra extends EditRecord
{
    protected static string $resource = ExpedicionCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar_albaran')
                ->label('📤 Importar albarán')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->visible(fn () => !empty($this->record->documento_path))
                ->url(fn () => route('filament.admin.pages.ocr-import') . '?from_expedicion=' . $this->record->id)
                ->tooltip('Enviar documento adjunto al importador OCR de albaranes'),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
