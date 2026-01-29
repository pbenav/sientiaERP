<?php

namespace App\Filament\Resources\AlbaranCompraResource\Pages;

use App\Filament\Resources\AlbaranCompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlbaranCompra extends CreateRecord
{
    protected static string $resource = AlbaranCompraResource::class;

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('importar')
                ->label('Importar desde imagen')
                ->icon('heroicon-o-camera')
                ->color('info')
                ->url(route('filament.admin.pages.ocr-import'))
                ->openUrlInNewTab(false),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo'] = 'albaran_compra';
        return $data;
    }

    // La lógica de creación automática se ha movido al OcrImportModal.
    // Este método queda limpio para la creación manual estándar de Filament.
}
