<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use App\Models\Expedicion;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ProcesarExpedicion extends Page
{
    protected static string $resource = ExpedicionResource::class;
    protected static string $view = 'filament.resources.expedicion-resource.pages.procesar-expedicion';
    protected static ?string $title = 'Procesar expedición con IA';

    public Expedicion $record;

    public function mount(int|string $record): void
    {
        $this->record = Expedicion::with(['compras.tercero'])->findOrFail($record);
    }

    public function getTitle(): string
    {
        return 'Procesar: ' . $this->record->nombre;
    }

    /**
     * URL de vuelta después de crear un albarán en OcrImport.
     * Se pasa como query param ?back= a OcrImport.
     */
    public function getBackUrl(): string
    {
        return ExpedicionResource::getUrl('procesar', ['record' => $this->record]);
    }

    /**
     * URL para enviar una compra concreta al importador OCR.
     */
    public function getOcrUrl(int $compraId): string
    {
        $back = urlencode($this->getBackUrl());
        return route('filament.admin.pages.ocr-import') . "?from_compra={$compraId}&back={$back}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('← Volver a la expedición')
                ->url(ExpedicionResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),
        ];
    }
}
