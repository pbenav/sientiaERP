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
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->record->confirmar();
                        $this->refreshFormData(['estado', 'numero']);
                        Notification::make()->title('Albarán confirmado')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('convertir_factura')
                ->label('Generar Factura')
                ->icon('heroicon-o-document-currency-euro')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'confirmado')
                ->requiresConfirmation()
                ->action(function () {
                    $factura = $this->record->convertirA('factura_compra');
                    Notification::make()->title('Factura creada')->success()->send();
                    return redirect()->route('filament.admin.resources.factura-compras.edit', $factura);
                }),

            Actions\Action::make('anular')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => in_array($this->record->estado, ['confirmado', 'procesado']) && !$this->record->tieneDocumentosDerivados())
                ->action(function () {
                    try {
                        $this->record->anular();
                        $this->refreshFormData(['estado']);
                        Notification::make()->title('Albarán anulado')->danger()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn() => route('documentos.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('ticket')
                ->label('Imprimir Ticket')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn() => route('documentos.ticket', $this->record))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->puedeEliminarse()),
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
