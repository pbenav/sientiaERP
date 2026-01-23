<?php

namespace App\Filament\Resources\AlbaranCompraResource\Pages;

use App\Filament\Resources\AlbaranCompraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlbaranesCompra extends ListRecords
{
    protected static string $resource = AlbaranCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar')
                ->label('Importar con Tesseract')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalContent(fn () => view('filament.resources.albaran-compra-resource.pages.ocr-modal-wrapper'))
                ->modalSubmitAction(false),
            Actions\CreateAction::make(),
        ];
    }
}
