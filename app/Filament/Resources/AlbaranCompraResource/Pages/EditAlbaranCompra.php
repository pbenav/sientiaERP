<?php

namespace App\Filament\Resources\AlbaranCompraResource\Pages;

use App\Filament\Resources\AlbaranCompraResource;
use App\Models\Documento;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAlbaranCompra extends EditRecord
{
    protected static string $resource = AlbaranCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_labels')
                ->label('Generar Etiquetas')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('label_format_id')
                        ->label('Formato de Etiqueta')
                        ->options(\App\Models\LabelFormat::where('activo', true)->pluck('nombre', 'id'))
                        ->required()
                        ->default(\App\Models\LabelFormat::where('activo', true)->first()?->id),
                ])
                ->action(function (Documento $record, array $data) {
                    $labelDoc = Documento::create([
                        'tipo' => 'etiqueta',
                        'estado' => 'borrador',
                        'user_id' => auth()->id(),
                        'fecha' => now(),
                        'label_format_id' => $data['label_format_id'],
                        'documento_origen_id' => $record->id,
                        'observaciones' => "Generado desde Albarán: " . ($record->numero ?? $record->referencia_proveedor),
                    ]);

                    foreach ($record->lineas as $linea) {
                        $labelDoc->lineas()->create([
                            'product_id' => $linea->product_id,
                            'codigo' => $linea->codigo ?: ($linea->product?->sku ?? $linea->product?->code ?? $linea->product?->barcode ?? null),
                            'descripcion' => $linea->descripcion,
                            'cantidad' => $linea->cantidad,
                            'unidad' => $linea->unidad,
                            'precio_unitario' => $linea->precio_unitario,
                        ]);
                    }

                    Notification::make()
                        ->title('Documento de etiquetas generado')
                        ->success()
                        ->send();
                    
                    return redirect(static::getResource()::getUrl('edit', ['record' => $record]));
                })
                ->visible(fn (Documento $record) => !Documento::where('tipo', 'etiqueta')
                    ->where('documento_origen_id', $record->id)
                    ->exists()),
            Actions\Action::make('print_labels')
                ->label('Imprimir Etiquetas')
                ->icon('heroicon-o-tag')
                ->color('success')
                ->url(function (Documento $record) {
                    $etiqueta = \App\Models\Documento::where('tipo', 'etiqueta')
                        ->where('documento_origen_id', $record->id)
                        ->first();
                    return $etiqueta ? route('etiquetas.pdf', ['record' => $etiqueta->id]) : '#';
                })
                ->visible(function (Documento $record) {
                    return \App\Models\Documento::where('tipo', 'etiqueta')
                        ->where('documento_origen_id', $record->id)
                        ->exists();
                })
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->recalcularTotales();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


}
