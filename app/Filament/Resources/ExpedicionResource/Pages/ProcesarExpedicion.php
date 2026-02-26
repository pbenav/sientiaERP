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

    // No type-hint del modelo: Livewire serializa mejor con mixed
    public mixed $record = null;

    /**
     * Filament puede pasar el {record} como ID (int/string) o como
     * modelo ya resuelto por route model binding. Aceptamos ambos.
     */
    public function mount($record): void
    {
        if ($record instanceof Expedicion) {
            $this->record = $record->load('compras.tercero');
        } else {
            $this->record = Expedicion::with(['compras.tercero'])->findOrFail($record);
        }
    }

    public function getTitle(): string
    {
        return 'Procesar: ' . ($this->record?->nombre ?? '—');
    }

    /** URL de retorno para OcrImport (?back=...) */
    public function getBackUrl(): string
    {
        return route(
            'filament.admin.resources.expedicions.procesar',
            ['record' => $this->record->id]
        );
    }

    /** URL completa al importador OCR con contexto de la compra */
    public function getOcrUrl(int $compraId): string
    {
        return route('filament.admin.pages.ocr-import')
            . '?from_compra=' . $compraId
            . '&back=' . urlencode($this->getBackUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('← Volver a la expedición')
                ->url(ExpedicionResource::getUrl('edit', ['record' => $this->record?->id]))
                ->color('gray'),
        ];
    }
}
