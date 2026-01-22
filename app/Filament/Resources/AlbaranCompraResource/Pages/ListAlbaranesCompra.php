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
                ->label('Importar desde imagen')
                ->icon('heroicon-o-camera')
                ->color('info')
                ->url(route('filament.admin.pages.ocr-import'))
                ->openUrlInNewTab(false),
            Actions\CreateAction::make(),
        ];
    }
}
