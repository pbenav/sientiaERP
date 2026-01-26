<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Livewire\WithFileUploads;

class OcrImport extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.ocr-import';
    protected static ?string $title = 'Importar Albarán desde Imagen';
    protected static ?string $navigationLabel = 'OCR Import';
    protected static bool $shouldRegisterNavigation = false; // Hidden from menu

    public ?array $data = [];
    public $rawText = '';
    public $showDataForm = false;
    public $isCreating = false;
    public $maxUploadSize;

    public $parsedData = [
        'date' => null,
        'total' => null,
        'nif' => null,
        'supplier' => null,
        'document_number' => null,
        'items' => [],
    ];

    public function mount(): void
    {
        $this->form->fill();
        $this->maxUploadSize = ini_get('upload_max_filesize');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('documento')
                    ->label('Subir Imagen del Albarán')
                    ->image()
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function processImage()
    {
        // Reuse the exact same logic from OcrImportModal
        $state = $this->data['documento'] ?? null;
        $path = null;
        
        if (is_array($state)) {
            $path = array_values($state)[0] ?? null;
        } else {
            $path = $state;
        }

        if (!$path) {
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('Debes subir una imagen primero.')
                ->danger()
                ->send();
            return;
        }

        try {
            $fullPath = null;

            if (is_object($path) && method_exists($path, 'getRealPath')) {
                $fullPath = $path->getRealPath();
            } elseif (is_string($path)) {
                $publicPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
                if (file_exists($publicPath)) {
                    $fullPath = $publicPath;
                } else {
                    $localPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    if (file_exists($localPath)) {
                        $fullPath = $localPath;
                    } else {
                        $storagePath = storage_path('app/' . $path);
                        if (file_exists($storagePath)) {
                            $fullPath = $storagePath;
                        } else {
                            if (file_exists($path)) {
                                $fullPath = $path;
                            }
                        }
                    }
                }
            }
            
            if (!$fullPath || !file_exists($fullPath)) {
                throw new \Exception("No se ha podido localizar el archivo temporal.");
            }
            
            $service = new \App\Services\AiDocumentParserService();
            $result = $service->extractFromImage($fullPath);
            
            $this->parsedData['date'] = $result['document_date'] ?? null;
            $this->parsedData['nif'] = $result['provider_nif'] ?? null;
            $this->parsedData['supplier'] = $result['provider_name'] ?? null;
            $this->parsedData['document_number'] = $result['document_number'] ?? null;
            $this->parsedData['total'] = null;
            $this->parsedData['matched_provider_id'] = $result['matched_provider_id'] ?? null;
            
            $formattedItems = [];
            if (!empty($result['items'])) {
                foreach ($result['items'] as $item) {
                     $formattedItems[] = [
                        'description' => $item['description'] ?? '',
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'matched_product_id' => $item['matched_product_id'] ?? null,
                    ];
                }
            }
            $this->parsedData['items'] = $formattedItems;
            
            $this->rawText = $result['raw_text'] ?? ($result['observaciones'] ?? 'Procesado por IA');

            $this->showDataForm = true;

            \Filament\Notifications\Notification::make()
                ->title('Procesamiento Completado')
                ->body('Datos extraídos correctamente.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error procesando imagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addItem()
    {
        $this->parsedData['items'][] = [
            'description' => '',
            'reference' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'matched_product_id' => null,
        ];
    }

    public function removeItem($index)
    {
        unset($this->parsedData['items'][$index]);
        $this->parsedData['items'] = array_values($this->parsedData['items']); // Reindex array
    }

    public function createDocument()
    {
        \Illuminate\Support\Facades\Log::info('OCR: createDocument called from OcrImport page.');

        // Identify final path
        $finalPath = null;
        try {
            $state = $this->data['documento'] ?? null;
            $currentPath = is_array($state) ? (array_values($state)[0] ?? null) : $state;

            if ($currentPath) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($currentPath)) {
                    $extension = pathinfo($currentPath, PATHINFO_EXTENSION);
                    $newFilename = 'albaran_' . time() . '_' . uniqid() . '.' . $extension;
                    $targetDir = 'documentos/images';
                    $targetPath = $targetDir . '/' . $newFilename;

                    // Copy file (optimization can be added later)
                    \Illuminate\Support\Facades\Storage::disk('public')->copy($currentPath, $targetPath);
                    
                    $finalPath = $targetPath;
                    \Illuminate\Support\Facades\Log::info('File stored', ['path' => $finalPath]);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error moving file: ' . $e->getMessage());
        }

        // Prepare Data for Creation
        $data = [
            'tipo' => 'albaran_compra',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => $this->parsedData['date'] ?? now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
            'tercero_id' => $this->parsedData['matched_provider_id'] ?? null,
            'referencia_proveedor' => (string) ($this->parsedData['document_number'] ?? 'REF-' . strtoupper(uniqid())),
            'archivo' => $finalPath,
        ];

        // LOGICA PROVEEDOR FICTICIO / FALLBACK
        $observaciones = [];
        if (empty($data['tercero_id'])) {
            $dummyProvider = \App\Models\Tercero::firstOrCreate(
                ['nif_cif' => '00000000T'],
                [
                    'nombre_comercial' => 'PROVEEDOR PENDIENTE DE ASIGNAR',
                    'razon_social' => 'PROVEEDOR GENERADO AUTOMATICAMENTE',
                    'activo' => true
                ]
            );

            if (!$dummyProvider->esProveedor()) {
                $tipoProv = \App\Models\TipoTercero::where('codigo', 'PRO')->first();
                if ($tipoProv) $dummyProvider->tipos()->syncWithoutDetaching([$tipoProv->id]);
            }

            $data['tercero_id'] = $dummyProvider->id;
            $observaciones[] = "AVISO: Proveedor no detectado. Asignado a 'PROVEEDOR PENDIENTE'.";
        }

        if (!empty($this->parsedData['supplier']) && empty($this->parsedData['matched_provider_id'])) {
            $observaciones[] = "Proveedor detectado por IA (no macheado): " . $this->parsedData['supplier'];
        }
        if (!empty($this->rawText)) {
            $observaciones[] = "--- OCR RAW TEXT ---\n" . $this->rawText;
        }
        $data['observaciones'] = implode("\n\n", $observaciones);

        try {
            // 1. Validate / Create Products
            if (!empty($this->parsedData['items']) && is_array($this->parsedData['items'])) {
                foreach ($this->parsedData['items'] as $index => &$item) {
                    if (empty($item['matched_product_id'])) {
                        // Try to find existing product by reference/code
                        $productRef = $item['reference'] ?? $item['product_code'] ?? null;
                        
                        if (!empty($productRef)) {
                            $existingProduct = \App\Models\Product::findByCode($productRef);
                            if ($existingProduct) {
                                $item['matched_product_id'] = $existingProduct->id;
                                \Illuminate\Support\Facades\Log::info('Product matched by code', [
                                    'code' => $productRef,
                                    'product_id' => $existingProduct->id
                                ]);
                                continue; // Skip creation, product found
                            }
                        }
                        
                        $desc = $item['description'] ?? 'Producto Desconocido';
                        if (empty($desc)) $desc = 'Producto sin descripción';

                        $price = $item['unit_price'] ?? 0;

                        // Create Product
                        try {
                            // Determine the product code/reference
                            $productRef = $item['reference'] ?? $item['product_code'] ?? null;
                            
                            // If no reference provided, generate one
                            if (empty($productRef)) {
                                $productRef = 'AUTO-' . strtoupper(uniqid());
                            }
                            
                            $newProduct = \App\Models\Product::create([
                                'name' => $desc,
                                'description' => $desc,
                                'price' => $price,
                                'tax_rate' => 21.00,
                                'active' => true,
                                'stock' => 0,
                                // Set all three fields to the same value
                                'sku' => $productRef,
                                'code' => $productRef,
                                'barcode' => $productRef,
                            ]);

                            $item['matched_product_id'] = $newProduct->id;
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error creating product: ' . $e->getMessage());
                        }
                    }
                }
            }

            // DIRECT CREATION
            $record = \App\Models\Documento::create($data);

            // Create Lines
            foreach ($this->parsedData['items'] as $item) {
                $qty = $item['quantity'] ?? 1;
                $price = $item['unit_price'] ?? 0;
                $desc = $item['description'] ?? 'Producto incorrecto';
                if (empty($desc)) $desc = 'Línea importada';

                $taxRate = 0.21;
                if (!empty($item['matched_product_id'])) {
                    $prod = \App\Models\Product::find($item['matched_product_id']);
                    if ($prod) {
                        $taxRate = $prod->tax_rate > 0 ? $prod->tax_rate / 100 : 0.21;
                    }
                }

                $record->lineas()->create([
                    'product_id' => $item['matched_product_id'] ?? null,
                    'descripcion' => $desc,
                    'cantidad' => $qty,
                    'unidad' => 'Ud',
                    'precio_unitario' => $price,
                    'descuento' => 0,
                    'subtotal' => $qty * $price,
                    'iva' => $taxRate * 100,
                    'importe_iva' => ($qty * $price) * $taxRate,
                    'irpf' => 0,
                    'importe_irpf' => 0,
                    'total' => ($qty * $price) * (1 + $taxRate),
                ]);
            }

            if (method_exists($record, 'recalcularTotales')) {
                $record->recalcularTotales();
                $record->save();
            }

            \Filament\Notifications\Notification::make()
                ->title('Albarán Creado Correctamente')
                ->body('Redirigiendo a la edición...')
                ->success()
                ->send();

            // Redirect to Edit Page
            return redirect()->route('filament.admin.resources.albaran-compras.edit', ['record' => $record]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OCR Creation Error: ' . $e->getMessage());

            \Filament\Notifications\Notification::make()
                ->title('Error Crítico')
                ->body('No se pudo crear el documento: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
