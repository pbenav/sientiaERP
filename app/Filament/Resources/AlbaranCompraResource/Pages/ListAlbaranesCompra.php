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
                ->label('Importar desde Imagen')
                ->icon('heroicon-o-camera')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('documento')
                        ->label('Foto del Albarán')
                        ->image()
                        ->required()
                        ->disk('public')
                        ->directory('imports/albaranes'),
                ])
                ->action(function (array $data) {
                    try {
                        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($data['documento']);
                        
                        $parser = app(\App\Services\AiDocumentParserService::class);
                        $extractedData = $parser->extractFromImage($path);

                        \Illuminate\Support\Facades\Cache::put(
                            'albaran_import_' . auth()->id(), 
                            $extractedData, 
                            now()->addMinutes(10)
                        );

                        // Notify and redirect
                        \Filament\Notifications\Notification::make()
                            ->title('Documento procesado por IA')
                            ->success()
                            ->send();

                        $this->redirect(AlbaranCompraResource::getUrl('create'));

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error al procesar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
