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

    public function mount(): void
    {
        $key = 'albaran_import_' . auth()->id();
        $importedData = \Illuminate\Support\Facades\Cache::pull($key);

        if ($importedData) {
            $data = [
                'tipo' => 'albaran_compra',
                'estado' => 'borrador',
                'user_id' => auth()->id(),
                'fecha' => $importedData['document_date'] ?? now(),
                'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
                'tercero_id' => $importedData['matched_provider_id'] ?? null,
                'referencia_proveedor' => $importedData['document_number'] ?? null,
                'archivo' => $importedData['document_image_path'] ?? null,
            ];

            $observaciones = [];
            if (!$importedData['found_provider'] && !empty($importedData['provider_name'])) {
                 $observaciones[] = "Proveedor detectado por IA: " . $importedData['provider_name'];
            }
            if (!empty($importedData['raw_text'])) {
                $observaciones[] = "--- OCR RAW TEXT ---\n" . $importedData['raw_text'];
            }
            $data['observaciones'] = implode("\n\n", $observaciones);

            try {
                $record = static::getModel()::create($data);

                if (!empty($importedData['items']) && is_array($importedData['items'])) {
                    foreach ($importedData['items'] as $item) {
                        $qty = $item['quantity'] ?? 1;
                        $price = $item['unit_price'] ?? 0;
                        
                        $taxRate = 0.21;
                        if ($item['matched_product_id']) {
                            $prod = \App\Models\Product::find($item['matched_product_id']);
                            if ($prod) {
                                $taxRate = $prod->tax_rate > 0 ? $prod->tax_rate / 100 : 0.21;
                            }
                        }

                        $record->lineas()->create([
                            'product_id' => $item['matched_product_id'],
                            'concepto' => $item['description'],
                            'cantidad' => $qty,
                            'precio_unitario' => $price,
                            'importe' => $qty * $price,
                            'iva' => $taxRate,
                        ]);
                    }
                }

                if (method_exists($record, 'recalcularTotales')) {
                    $record->recalcularTotales();
                    $record->save();
                }

                \Filament\Notifications\Notification::make()
                    ->title('Albarán creado desde imagen')
                    ->body('Revise los datos importados antes de confirmar.')
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                return;

            } catch (\Exception $e) {
                // If creation fails, notify and fill form instead
                \Filament\Notifications\Notification::make()
                    ->title('Error al crear borrador automático')
                    ->body('Se han precargado los datos, pero no se pudo guardar: ' . $e->getMessage())
                    ->warning()
                    ->persistent()
                    ->send();
                
                $this->form->fill($data);
                return;
            }
        }

        // Standard Manual Flow Fallback: try to autocreate draft or just show form
        try {
            $data = [
                'tipo' => 'albaran_compra',
                'estado' => 'borrador',
                'user_id' => auth()->id(),
                'fecha' => now(),
                'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
                // 'tercero_id' => null implied
            ];

            // If we are strictly requiring Auto-Draft, we try create.
            // But if third party is required, this fails.
            // Let's check if we can just fill form for manual entry.
            // If the user expects Auto-Draft behavior (as per original code), we try it.
            $record = static::getModel()::create($data);
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));

        } catch (\Exception $e) {
            // Fallback to standard CreateRecord behavior if AutoDraft fails
            \Filament\Notifications\Notification::make()
                ->title('Modo Creación Manual')
                ->body('No se pudo crear el borrador automático. Por favor rellene el formulario.')
                ->warning()
                ->send();
                
            $this->authorizeAccess();
            $this->fillForm();
            // We strip 'tercero_id' requirement here by just filling defaults?
            $this->form->fill([
                'fecha' => now(),
                'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
            ]);
        }
    }
}
