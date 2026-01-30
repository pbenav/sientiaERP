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
    public $labelFormats = [];
    public $generateLabels = false;
    public $selectedLabelFormatId = null;
    public $print_all_labels = true;

    public $parsedData = [
        'date' => null,
        'total' => null,
        'nif' => null,
        'supplier' => null,
        'supplier_id' => null,
        'document_number' => null,
        'items' => [],
    ];
    
    public $suppliers = [];
    public $displayUppercase = false;

    public function mount(): void
    {
        $this->form->fill();
        $this->maxUploadSize = ini_get('upload_max_filesize');
        
        // Cargar proveedores
        $this->suppliers = \App\Models\Tercero::whereHas('tipos', function($q) {
            $q->where('codigo', 'PRO');
        })->orderBy('nombre_comercial')->get();
        
        // Establecer proveedor por defecto
        $defaultSupplierId = \App\Models\Setting::get('default_supplier_id');
        if ($defaultSupplierId) {
            $this->parsedData['supplier_id'] = $defaultSupplierId;
        }
        
        // Cargar preferencia de mayúsculas
        $this->displayUppercase = \App\Models\Setting::get('display_uppercase', 'false') === 'true';

        // Cargar formatos de etiquetas
        $this->labelFormats = \App\Models\LabelFormat::where('activo', true)->get();
        $this->selectedLabelFormatId = $this->labelFormats->first()?->id;
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
                $defaultMargin = (float)\App\Models\Setting::get('default_commercial_margin', 30);
                $defaultTaxRate = (float)\App\Models\Setting::get('default_tax_rate', 21);
                
                foreach ($result['items'] as $item) {
                    $purchasePrice = (float)($item['unit_price'] ?? 0);
                    $discount = (float)($item['discount'] ?? 0);
                    $netCost = $purchasePrice * (1 - ($discount / 100));
                    $margin = $defaultMargin;
                    
                    // Validar que el margen no sea 100% o mayor para evitar división por cero
                    if ($margin >= 100) {
                        $margin = 99;
                    }
                    
                    // Precio sin IVA INICIAL (Calculado con margen deseado sobre COSTE NETO)
                    $initialPriceWithoutVat = $netCost / (1 - ($margin / 100));
                    
                    // Precio de venta TEÓRICO con IVA
                    $theoreticalSalePrice = $initialPriceWithoutVat * (1 + ($defaultTaxRate / 100));
                    
                    // APLICAR PRECIO PSICOLÓGICO
                    $salePrice = $this->getClosestPsychologicalPrice($theoreticalSalePrice);
                    
                    // RECALCULAR TODO HACIA ATRÁS DESDE EL PRECIO FINAL
                    $priceWithoutVat = $salePrice / (1 + ($defaultTaxRate / 100));
                    
                    // Nuevo Margen Real
                    // Margin = 1 - (NetCost / PriceWithoutVat)
                    if ($priceWithoutVat > 0) {
                        $margin = (1 - ($netCost / $priceWithoutVat)) * 100;
                    } else {
                        $margin = 0;
                    }
                    
                    // Beneficio Real
                    $benefit = $priceWithoutVat - $netCost;
                    
                    // Importe IVA Real
                    $vatAmount = $priceWithoutVat * ($defaultTaxRate / 100);
                    
                    $formattedItems[] = [
                        'description' => $item['description'] ?? '',
                        'reference' => $item['reference'] ?? $item['product_code'] ?? '',
                        'product_code' => $item['product_code'] ?? $item['reference'] ?? '',
                        'quantity' => (float)($item['quantity'] ?? 1),
                        'unit_price' => $purchasePrice,
                        'discount' => $discount,
                        'margin' => round($margin, 3), // Redondeo interno a 3 decimales
                        'benefit' => $benefit,
                        'vat_rate' => $defaultTaxRate,
                        'vat_amount' => $vatAmount,
                        'sale_price' => $salePrice,
                        'matched_product_id' => $item['matched_product_id'] ?? null,
                        'print_label' => true,
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

    protected function getClosestPsychologicalPrice(float $price): float
    {
        // Estrategia: "Decimals must be 90 upwards (90, 95 or 99)"
        // "closest possible to the ten (just below)"
        
        $integerPart = floor($price);
        
        // Generamos candidatos para el entero actual y el anterior
        $candidates = [
             // Entero actual
            $integerPart + 0.90,
            $integerPart + 0.95,
            $integerPart + 0.99,
            // Entero anterior (por si 15.05 debe bajar a 14.99)
            $integerPart - 1 + 0.90,
            $integerPart - 1 + 0.95,
            $integerPart - 1 + 0.99,
        ];

        $closest = $price;
        $minDiff = PHP_FLOAT_MAX;

        foreach ($candidates as $candidate) {
            // Evitar precios negativos
            if ($candidate < 0) continue;
            
            $diff = abs($price - $candidate);
            
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $candidate;
            }
        }
        
        return $closest;
    }

    public function addItem()
    {
        $defaultMargin = (float)\App\Models\Setting::get('default_commercial_margin', 30);
        $defaultTaxRate = (float)\App\Models\Setting::get('default_tax_rate', 21);
        
        // Validar que el margen no sea 100% o mayor
        if ($defaultMargin >= 100) {
            $defaultMargin = 99;
        }
        
        // Precio base (asumimos 0 al crear manual)
        $purchasePrice = 0;

        // Precio sin IVA
        // Al ser coste 0, cualquier precio de venta da margen 100%.
        // Ponemos valores por defecto para que el usuario edite.
        $salePrice = 0.00;
        $vatAmount = 0;
        $benefit = 0;
        
        $this->parsedData['items'][] = [
            'description' => '',
            'reference' => '',
            'quantity' => 1.0,
            'unit_price' => 0.0,
            'discount' => 0.0,
            'margin' => $defaultMargin,
            'benefit' => $benefit,
            'vat_rate' => $defaultTaxRate,
            'vat_amount' => $vatAmount,
            'sale_price' => $salePrice,
            'matched_product_id' => null,
            'print_label' => true,
        ];
    }

    public function updatedPrintAllLabels($value)
    {
        foreach ($this->parsedData['items'] as $index => $item) {
            $this->parsedData['items'][$index]['print_label'] = $value;
        }
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
            'tercero_id' => $this->parsedData['supplier_id'] ?? $this->parsedData['matched_provider_id'] ?? null,
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
                                
                                // Actualizar producto existente con nuevos datos
                                $purchasePriceGross = $item['unit_price'] ?? 0;
                                $discount = $item['discount'] ?? 0;
                                $purchasePriceNet = $purchasePriceGross * (1 - ($discount / 100));
                                $margin = $item['margin'] ?? 30;
                                
                                // El precio de venta (PVP) es el calculado por nosotros (psicológico o editado)
                                $retailPrice = $item['sale_price'] ?? \App\Models\Product::calculateRetailPrice($purchasePriceNet, $margin);
                                
                                $docNumber = $this->parsedData['document_number'] ?? null;
                                
                                $existingProduct->name = $item['description'] ?? $existingProduct->name;
                                $existingProduct->description = $item['description'] ?? $existingProduct->description;
                                $existingProduct->price = $retailPrice;
                                $existingProduct->addPurchaseHistory(
                                    $purchasePriceGross,
                                    $discount,
                                    $purchasePriceNet,
                                    $margin,
                                    $docNumber
                                );
                                $existingProduct->save();
                                
                                \Illuminate\Support\Facades\Log::info('Product updated with new data', [
                                    'code' => $productRef,
                                    'product_id' => $existingProduct->id,
                                    'new_pvp' => $retailPrice,
                                    'new_net_cost' => $purchasePriceNet
                                ]);
                                continue; // Skip creation, product updated
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
                            
                            // Detalle de precios para nuevo producto
                            $purchasePriceGross = $item['unit_price'] ?? 0;
                            $discount = $item['discount'] ?? 0;
                            $purchasePriceNet = $purchasePriceGross * (1 - ($discount / 100));
                            $margin = $item['margin'] ?? 30;
                            
                            // El PVP es el calculado o el editado manual en la tabla
                            $retailPrice = $item['sale_price'] ?? \App\Models\Product::calculateRetailPrice($purchasePriceNet, $margin);
                            
                            $newProduct = \App\Models\Product::create([
                                'name' => $desc,
                                'description' => $desc,
                                'price' => $retailPrice, // PVP final
                                'tax_rate' => 21.00,
                                'active' => true,
                                'stock' => 0,
                                'sku' => $productRef,
                                'code' => $productRef,
                                'barcode' => $productRef,
                            ]);

                            $docNumber = $this->parsedData['document_number'] ?? null;
                            $newProduct->addPurchaseHistory(
                                $purchasePriceGross,
                                $discount,
                                $purchasePriceNet,
                                $margin,
                                $docNumber
                            );
                            $newProduct->save();

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
                $discount = $item['discount'] ?? 0;
                $desc = $item['description'] ?? 'Producto incorrecto';
                if (empty($desc)) $desc = 'Línea importada';

                $taxRate = 0.21;
                $prod = null; // Reset $prod to avoid loop leakage
                
                if (!empty($item['matched_product_id'])) {
                    $prod = \App\Models\Product::find($item['matched_product_id']);
                    if ($prod) {
                        $taxRate = $prod->tax_rate > 0 ? $prod->tax_rate / 100 : 0.21;
                    }
                }

                $subtotal = $qty * $price;
                if ($discount > 0) {
                    $subtotal = $subtotal * (1 - ($discount / 100));
                }

                $record->lineas()->create([
                    'product_id' => $item['matched_product_id'] ?? null,
                    'codigo' => !empty($item['reference']) ? $item['reference'] : ($prod?->sku ?? $prod?->code ?? null),
                    'descripcion' => $desc,
                    'cantidad' => $qty,
                    'unidad' => 'Ud',
                    'precio_unitario' => $price,
                    'descuento' => $discount,
                    'subtotal' => $subtotal,
                    'iva' => $taxRate * 100,
                    'importe_iva' => $subtotal * $taxRate,
                    'irpf' => 0,
                    'importe_irpf' => 0,
                    'total' => $subtotal * (1 + $taxRate),
                ]);
            }

            if (method_exists($record, 'recalcularTotales')) {
                $record->recalcularTotales();
                $record->save();
            }

            // REDIRECT OR ADDITIONAL ACTIONS
            if ($this->generateLabels && $this->selectedLabelFormatId) {
                $selectedItems = array_filter($this->parsedData['items'], fn($item) => $item['print_label'] ?? false);
                
                if (count($selectedItems) > 0) {
                    $labelDoc = \App\Models\Documento::create([
                        'tipo' => 'etiqueta',
                        'estado' => 'borrador',
                        'user_id' => auth()->id(),
                        'fecha' => now(),
                        'label_format_id' => $this->selectedLabelFormatId,
                        'documento_origen_id' => $record->id,
                        'observaciones' => "Generado desde importación OCR del Albarán: " . ($record->numero ?? $record->referencia_proveedor),
                    ]);

                    foreach ($selectedItems as $item) {
                        $qty = (float)($item['quantity'] ?? 1);
                        $desc = $item['description'] ?? 'Línea importada';
                        $prod = !empty($item['matched_product_id']) ? \App\Models\Product::find($item['matched_product_id']) : null;
                    
                        $labelDoc->lineas()->create([
                            'product_id' => $item['matched_product_id'] ?? null,
                            'codigo' => !empty($item['reference']) ? $item['reference'] : ($prod?->sku ?? $prod?->code ?? $prod?->barcode ?? null),
                            'descripcion' => $desc,
                            'cantidad' => $qty,
                            'unidad' => 'Ud',
                            'precio_unitario' => $item['unit_price'] ?? 0,
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Documento de Etiquetas Creado')
                        ->body('Se ha generado un documento de etiquetas asociado.')
                        ->success()
                        ->send();
                }
            }

            \Filament\Notifications\Notification::make()
                ->title('Albarán Creado Correctamente')
                ->body('Redirigiendo a la edición...')
                ->success()
                ->send();

            // Redirect to Edit Page of the Albarán
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
