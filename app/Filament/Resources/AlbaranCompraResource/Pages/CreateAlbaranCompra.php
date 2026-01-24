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
        \Illuminate\Support\Facades\Log::info('CreateAlbaran: Checking cache', ['key' => $key]);
        
        $importedData = \Illuminate\Support\Facades\Cache::pull($key);
        
        \Illuminate\Support\Facades\Log::info('CreateAlbaran: Data found?', [
            'found' => (bool)$importedData, 
            'image' => $importedData['document_image_path'] ?? 'none'
        ]);

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

            $record = static::getModel()::create($data);

            if (!empty($importedData['items']) && is_array($importedData['items'])) {
                foreach ($importedData['items'] as $item) {
                    $qty = $item['quantity'] ?? 1;
                    $price = $item['unit_price'] ?? 0;
                    
                    // Attempt to fetch product tax if matched
                    $taxRate = 0.21; // Default
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
        }

        $data = [
            'tipo' => 'albaran_compra',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
        ];

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
