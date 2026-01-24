<?php

namespace App\Filament\Resources\AlbaranCompraResource\Pages;

use App\Filament\Resources\AlbaranCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlbaranCompra extends CreateRecord
{
    protected static string $resource = AlbaranCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('importar')
                ->label('Importar con Tesseract')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalContent(fn () => view('filament.resources.albaran-compra-resource.pages.ocr-modal-wrapper'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false),
        ];
    }

    // La lógica de creación automática se ha movido al OcrImportModal.
    // Este método queda limpio para la creación manual estándar de Filament.
}
