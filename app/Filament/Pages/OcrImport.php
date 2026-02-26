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
    public $startRow = 1;
    public $startColumn = 1;

    public $parsedData = [
        'date' => null,
        'total' => null,
        'nif' => null,
        'supplier' => null,
        'supplier_id' => null,
        'document_number' => null,
        'subtotal' => 0.0,
        'total_discount' => 0.0,
        'total_units' => 0.0,
        'total_amount' => 0.0,
        'items' => [],
    ];
    
    public $suppliers = [];
    public $displayUppercase = false;
    public $showCreateSupplierModal = false;
    public $newSupplier = [
        'nombre_comercial' => '',
        'nif_cif' => '',
        'email' => '',
        'telefono' => '',
    ];

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

    public function createSupplier(): void
    {
        $validated = $this->validate([
            'newSupplier.nombre_comercial' => 'required|string|max:255',
            'newSupplier.nif_cif'          => 'nullable|string|max:20',
            'newSupplier.email'            => 'nullable|email|max:255',
            'newSupplier.telefono'         => 'nullable|string|max:20',
        ], [], [
            'newSupplier.nombre_comercial' => 'Nombre Comercial',
            'newSupplier.nif_cif'          => 'NIF/CIF',
            'newSupplier.email'            => 'Email',
            'newSupplier.telefono'         => 'Teléfono',
        ]);

        try {
            $tercero = \App\Models\Tercero::create($this->newSupplier);
            $tipoProveedor = \App\Models\TipoTercero::where('codigo', 'PRO')->first();
            if ($tipoProveedor) {
                $tercero->tipos()->attach($tipoProveedor);
            }

            // Recargar la lista y seleccionar el nuevo proveedor
            $this->suppliers = \App\Models\Tercero::whereHas('tipos', fn($q) => $q->where('codigo', 'PRO'))
                ->orderBy('nombre_comercial')->get();
            $this->parsedData['supplier_id'] = $tercero->id;

            // Resetear formulario y cerrar modal
            $this->newSupplier = ['nombre_comercial' => '', 'nif_cif' => '', 'email' => '', 'telefono' => ''];
            $this->showCreateSupplierModal = false;

            \Filament\Notifications\Notification::make()
                ->title('Proveedor creado')
                ->success()
                ->body("Se ha creado el proveedor «{$tercero->nombre_comercial}» y se ha seleccionado.")
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al crear proveedor')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
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
            $this->parsedData['subtotal'] = $result['subtotal'] ?? 0.0;
            $this->parsedData['total_discount'] = $result['total_discount'] ?? 0.0;
            $this->parsedData['total_units'] = $result['total_units'] ?? 0.0;
            $this->parsedData['total_amount'] = $result['total'] ?? 0.0;
            $this->parsedData['matched_provider_id'] = $result['matched_provider_id'] ?? null;
            
            $formattedItems = [];
            if (!empty($result['items'])) {
                $defaultMargin = (float)\App\Models\Setting::get('default_profit_percentage', 60);
                $defaultTaxRate = (float)\App\Models\Setting::get('default_tax_rate', 21);
                $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                
                foreach ($result['items'] as $item) {
                    $purchasePrice = (float)($item['unit_price'] ?? 0);
                    $discount = (float)($item['discount'] ?? 0);
                    $netCost = $purchasePrice * (1 - ($discount / 100));
                    $margin = $defaultMargin;
                    $taxRate = (float)($item['vat_rate'] ?? $defaultTaxRate);
                    
                    if ($method === 'from_sale' && $margin >= 100) $margin = 99.99;
                    
                    $priceWithoutVat = \App\Models\Product::calculateSalePriceFromMargin($netCost, $margin, $method);
                    $theoreticalSalePrice = $priceWithoutVat * (1 + ($taxRate / 100));
                    $salePrice = $this->getClosestPsychologicalPrice($theoreticalSalePrice);
                    $priceWithoutVat = $salePrice / (1 + ($taxRate / 100));
                    
                    $margin = \App\Models\Product::calculateMarginFromPrices($netCost, $priceWithoutVat, $method);
                    
                    $benefit = $priceWithoutVat - $netCost;
                    $vatAmount = $priceWithoutVat * ($taxRate / 100);
                    
                    // Sanitize description
                    $description = trim($item['description'] ?? '');
                    // Remove any punctuation, symbols or whitespace at the beginning or end
                    $description = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $description);
                    // Standardize internal spaces and remove weird characters
                    $description = preg_replace('/[^\w\s\á\é\í\ó\ú\Á\É\Í\Ó\Ú\ñ\Ñ\.,\-]/u', ' ', $description);
                    $description = preg_replace('/\s+/', ' ', trim($description));

                    // Sanitize reference/code
                    $reference = trim($item['reference'] ?? $item['product_code'] ?? '');
                    // Remove any punctuation, symbols or whitespace at the beginning or end
                    $reference = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $reference);
                    // More restrictive for codes: only keep alphanumeric, hyphens and dots internally
                    $reference = preg_replace('/[^\w\-\.]/', '', $reference);
                    $reference = strtoupper(trim($reference));

                    $formattedItems[] = [
                        'description' => $description,
                        'reference' => $reference,
                        'product_code' => $reference,
                        'quantity' => (float)($item['quantity'] ?? 1),
                        'unit_price' => round($purchasePrice, 2),
                        'discount' => round($discount, 2),
                        'margin' => round($margin, 2),
                        'benefit' => round($benefit, 2),
                        'vat_rate' => round($taxRate, 2),
                        'vat_amount' => round($vatAmount, 2),
                        'sale_price' => round($salePrice, 2),
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
            'product_code' => '',
            'quantity' => 1.0,
            'unit_price' => 0.0,
            'discount' => 0.0,
            'margin' => $defaultMargin,
            'benefit' => 0.0,
            'vat_rate' => $defaultTaxRate,
            'vat_amount' => 0.0,
            'sale_price' => 0.0,
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

    protected function validateCurrentData(): bool
    {
        if (empty($this->parsedData['items'])) {
            \Filament\Notifications\Notification::make()
                ->title('Error de Validación')
                ->body('Debe haber al menos una línea de producto.')
                ->danger()
                ->send();
            return false;
        }

        foreach ($this->parsedData['items'] as $index => $item) {
            $lineNum = $index + 1;
            
            if (empty(trim($item['description'] ?? ''))) {
                \Filament\Notifications\Notification::make()
                    ->title('Error en línea ' . $lineNum)
                    ->body('La descripción es obligatoria.')
                    ->danger()
                    ->send();
                return false;
            }

            if (!is_numeric($item['quantity'] ?? null)) {
                \Filament\Notifications\Notification::make()
                    ->title('Error en línea ' . $lineNum)
                    ->body('La cantidad debe ser un número válido.')
                    ->danger()
                    ->send();
                return false;
            }

            if (!is_numeric($item['unit_price'] ?? null)) {
                \Filament\Notifications\Notification::make()
                    ->title('Error en línea ' . $lineNum)
                    ->body('El precio unitario debe ser un número válido.')
                    ->danger()
                    ->send();
                return false;
            }
        }

        return true;
    }

    public function createDocument()
    {
        \Illuminate\Support\Facades\Log::info('OCR: createDocument called from OcrImport page.');

        if (!$this->validateCurrentData()) {
            return;
        }

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

        $supplierId = $this->parsedData['supplier_id'] ?? null;
        if ($supplierId === "") $supplierId = null;

        $data = [
            'tipo' => 'albaran_compra',
            'estado' => 'borrador',
            'user_id' => auth()->id(),
            'fecha' => $this->parsedData['date'] ?? now(),
            'serie' => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
            'tercero_id' => $supplierId ?? $this->parsedData['matched_provider_id'] ?? null,
            'referencia_proveedor' => (string) ($this->parsedData['document_number'] ?? 'REF-' . strtoupper(uniqid())),
            'subtotal' => (float) ($this->parsedData['subtotal'] ?? 0),
            'descuento' => (float) ($this->parsedData['total_discount'] ?? 0),
            'total' => (float) ($this->parsedData['total_amount'] ?? 0),
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
        if (!empty($this->parsedData['total_units'])) {
            $observaciones[] = "Unidades totales extraídas: " . $this->parsedData['total_units'];
        }
        if (!empty($this->rawText)) {
            $observaciones[] = "--- OCR RAW TEXT ---\n" . $this->rawText;
        }
        $data['observaciones'] = implode("\n\n", $observaciones);

        try {
            // 1. Validate / Create Products
            // 1. Process Products (Update existing or Create new)
            if (!empty($this->parsedData['items']) && is_array($this->parsedData['items'])) {
                foreach ($this->parsedData['items'] as $index => &$item) {
                    // Try to find product if not matched yet
                    if (empty($item['matched_product_id'])) {
                        $productRef = $item['reference'] ?? $item['product_code'] ?? null;
                        if (!empty($productRef)) {
                            $existingProduct = \App\Models\Product::findByCode($productRef);
                            if ($existingProduct) {
                                $item['matched_product_id'] = $existingProduct->id;
                            }
                        }
                    }

                    if (!empty($item['matched_product_id'])) {
                        // UPDATE EXISTING PRODUCT
                        $product = \App\Models\Product::find($item['matched_product_id']);
                        if ($product) {
                            $purchasePriceGross = (float)($item['unit_price'] ?? 0);
                            $discount = (float)($item['discount'] ?? 0);
                            $purchasePriceNet = $purchasePriceGross * (1 - ($discount / 100));
                            $margin = (float)($item['margin'] ?? 60);
                            $retailPrice = (float)($item['sale_price'] ?? 0);
                            
                            $product->name = !empty($item['description']) ? $item['description'] : $product->name;
                            $product->price = $retailPrice;
                            $product->purchase_price = $purchasePriceNet; // Set purchase price
                            $product->tax_rate = (float)($item['vat_rate'] ?? $product->tax_rate);
                            $product->addPurchaseHistory(
                                $purchasePriceGross,
                                $discount,
                                $purchasePriceNet,
                                $margin,
                                $this->parsedData['document_number'] ?? null
                            );
                            $product->save();
                        }
                    } else {
                        // CREATE NEW PRODUCT
                        try {
                            $productRef = $item['reference'] ?? $item['product_code'] ?? 'AUTO-' . strtoupper(uniqid());
                            $desc = !empty($item['description']) ? $item['description'] : 'Producto sin descripción';
                            
                            $purchasePriceGross = (float)($item['unit_price'] ?? 0);
                            $discount = (float)($item['discount'] ?? 0);
                            $purchasePriceNet = $purchasePriceGross * (1 - ($discount / 100));
                            $margin = (float)($item['margin'] ?? 60);
                            $retailPrice = (float)($item['sale_price'] ?? 0);

                            $newProduct = \App\Models\Product::create([
                                'name' => $desc,
                                'description' => $desc,
                                'price' => $retailPrice,
                                'purchase_price' => $purchasePriceNet, // Set purchase price
                                'tax_rate' => (float)($item['vat_rate'] ?? 21.00),
                                'active' => true,
                                'stock' => 0,
                                'sku' => $productRef,
                                'barcode' => $productRef,
                            ]);

                            $newProduct->addPurchaseHistory(
                                $purchasePriceGross,
                                $discount,
                                $purchasePriceNet,
                                $margin,
                                $this->parsedData['document_number'] ?? null
                            );
                            $newProduct->save();
                            $item['matched_product_id'] = $newProduct->id;
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error creating product in OCR: ' . $e->getMessage());
                        }
                    }
                }
                unset($item);
            }

            // DIRECT CREATION
            $record = \App\Models\Documento::create($data);

            // Create Lines
            foreach ($this->parsedData['items'] as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                $desc = !empty($item['description']) ? $item['description'] : 'Línea importada';
                $taxRate = (float)($item['vat_rate'] ?? 21);
                
                $prodId = $item['matched_product_id'] ?? null;
                $ref = !empty($item['reference']) ? $item['reference'] : 'REF-TEMP';

                $subtotal = $qty * $price;
                if ($discount > 0) {
                    $subtotal = $subtotal * (1 - ($discount / 100));
                }

                $record->lineas()->create([
                    'product_id' => $prodId,
                    'codigo' => $ref,
                    'descripcion' => $desc,
                    'cantidad' => $qty,
                    'unidad' => 'Ud',
                    'precio_unitario' => $price,
                    'descuento' => $discount,
                    'subtotal' => $subtotal,
                    'iva' => $taxRate,
                    'importe_iva' => $subtotal * ($taxRate / 100),
                    'irpf' => 0,
                    'importe_irpf' => 0,
                    'total' => $subtotal * (1 + ($taxRate / 100)),
                ]);
            }

            if (method_exists($record, 'recalcularTotales')) {
                $record->recalcularTotales();
                $record->save();
            }

            // CONFIRM DOCUMENT TO UPDATE STOCK
            try {
                if (method_exists($record, 'confirmar')) {
                    $record->confirmar();
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error confirming document from OCR: ' . $e->getMessage());
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
                        'fila_inicio' => $this->startRow,
                        'columna_inicio' => $this->startColumn,
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
