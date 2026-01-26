<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Cache;
use Livewire\WithFileUploads;

class OcrImportModal extends Component implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    public ?array $data = [];
    
    // We keep $file for the logic, but it will be managed by Filament Form
    public $documento; 

    public $rawText = '';
    public $isProcessing = false;
    public $showDataForm = false;
    public $isCreating = false; // Estado para mostrar logs

    protected function streamLog($message, $type = 'info')
    {
        $color = match($type) {
            'success' => 'text-green-400',
            'warning' => 'text-yellow-400',
            'error' => 'text-red-400',
            default => 'text-gray-300'
        };
        
        $html = "<div class='{$color}'>[".date('H:i:s')."] {$message}</div>";
        $this->stream('ocr-creation-logs', $html, true); // true = append
    }
    public $parsedData = [
        'date' => null,
        'total' => null,
        'nif' => null,
        'supplier' => null,
        'document_number' => null,
        'items' => [],
    ];

    public $maxUploadSize;

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
        $state = $this->data['documento'] ?? null;
        $path = null;
        
        // Handle array or single (File object or path string)
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

        $this->isProcessing = true;

        try {
            $fullPath = null;

            // Case 1: It's an UploadedFile object (Livewire temporary file)
            if (is_object($path) && method_exists($path, 'getRealPath')) {
                $fullPath = $path->getRealPath();
            } 
            // Case 2: It's a string path
            elseif (is_string($path)) {
                // Try finding it on public disk (configured disk)
                $publicPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
                if (file_exists($publicPath)) {
                    $fullPath = $publicPath;
                } else {
                    // Try finding it on local (default) disk
                    $localPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    if (file_exists($localPath)) {
                        $fullPath = $localPath;
                    } else {
                         // Check raw storage path
                        $storagePath = storage_path('app/' . $path);
                        if (file_exists($storagePath)) {
                            $fullPath = $storagePath;
                        } else {
                             // Last resort: maybe it's already absolute?
                            if (file_exists($path)) {
                                $fullPath = $path;
                            }
                        }
                    }
                }
            }
            
            if (!$fullPath || !file_exists($fullPath)) {
                throw new \Exception("No se ha podido localizar el archivo temporal. Intenta subirlo de nuevo.");
            }
            
            // Usar el servicio de IA
            $service = new \App\Services\AiDocumentParserService();
            $result = $service->extractFromImage($fullPath);
            
            // Mapear resultados
            $this->parsedData['date'] = $result['document_date'] ?? null;
            $this->parsedData['nif'] = $result['provider_nif'] ?? null;
            $this->parsedData['supplier'] = $result['provider_name'] ?? null;
            $this->parsedData['document_number'] = $result['document_number'] ?? null;
            $this->parsedData['total'] = null; // AI Service might not explicitly parse total yet, or it's in items
            $this->parsedData['matched_provider_id'] = $result['matched_provider_id'] ?? null;
            
            // Items
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
            
            // Raw text might be in 'observaciones' if fallback was used
            $this->rawText = $result['raw_text'] ?? ($result['observaciones'] ?? 'Procesado por IA');

            $this->showDataForm = true;
            \Illuminate\Support\Facades\Log::info('OCR process success. showDataForm set to true.');

            \Filament\Notifications\Notification::make()
                ->title('Procesamiento Completado')
                ->body('Datos extraídos. Por favor revisa y crea el albarán.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error procesando imagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            $this->addError('data.documento', $e->getMessage());
        }

        $this->isProcessing = false;
    }

    // parseText y extractItems eliminados ya que la lógica está ahora en el servicio


    // extractItems removido

    public function resetState()
    {
        $this->showDataForm = false;
        $this->rawText = '';
        $this->parsedData = [
            'date' => null,
            'total' => null,
            'nif' => null,
            'supplier' => null,
            'document_number' => null,
            'items' => [],
        ];
        $this->form->fill(); // Clear file upload
    }

    public function createDocument()
    {
        // 1. Log inicial
        \Illuminate\Support\Facades\Log::info('OCR: createDocument called.');

        // Identify final path
        $finalPath = null;
        try {
            $state = $this->data['documento'] ?? null;
            $currentPath = is_array($state) ? (array_values($state)[0] ?? null) : $state;

            if ($currentPath) {
                // If temporary usage, persist. Assuming standard upload.
                // Re-verify existence to be safe
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($currentPath)) {
                     // Since Livewire/Filament temp uploads are usually moved automatically on form save, 
                     // but here we are manual. Let's copy to "documentos/images".
                     $extension = pathinfo($currentPath, PATHINFO_EXTENSION);
                     $newFilename = 'albaran_' . time() . '_' . uniqid() . '.' . $extension;
                     $targetDir = 'documentos/images';
                     $targetPath = $targetDir . '/' . $newFilename;

                     // Optimize and save
                     $this->optimizeAndRefactorImage($currentPath, $targetPath);
                     
                     $finalPath = $targetPath;
                     \Illuminate\Support\Facades\Log::info('File stored and optimized', ['path' => $finalPath]);
                } else {
                     // Fallback: Use raw path if it was already local or full path
                     $finalPath = $currentPath; 
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
            $this->isCreating = true;
            $this->streamLog("Iniciando proceso de creación...", 'info');

            // 1. Validate / Create Products
            if (!empty($this->parsedData['items']) && is_array($this->parsedData['items'])) {
                foreach ($this->parsedData['items'] as $index => &$item) {
                    if (empty($item['matched_product_id'])) {
                        $desc = $item['description'] ?? 'Producto Desconocido';
                        if (empty($desc)) $desc = 'Producto sin descripción';
                        
                        $price = $item['unit_price'] ?? 0;
                        
                        $this->streamLog("Producto no existe: '{$desc}'. Creando...", 'warning');
                        
                        // Create Product
                        try {
                            $newProduct = \App\Models\Product::create([
                                'name' => $desc,
                                'description' => $desc, // Use name as desc
                                'price' => $price,
                                'tax_rate' => 21.00, // Default tax
                                'active' => true,
                                'stock' => 0,
                                'sku' => 'AUTO-' . strtoupper(uniqid()), // Temp SKU
                            ]);
                            
                            $item['matched_product_id'] = $newProduct->id;
                            $this->streamLog(" > Creado OK (SKU: {$newProduct->sku})", 'success');
                            
                        } catch (\Exception $e) {
                            $this->streamLog(" > Error creando producto: " . $e->getMessage(), 'error');
                        }
                    }
                }
            }

            $this->streamLog("Creando cabecera del documento...", 'info');
            // DIRECT CREATION (No Session)
            $record = \App\Models\Documento::create($data);

            $this->streamLog("Documento #{$record->id} creado. Añadiendo líneas...", 'info');

            // Create Lines
                foreach ($this->parsedData['items'] as $item) {
                    $qty = $item['quantity'] ?? 1;
                    $price = $item['unit_price'] ?? 0;
                    $desc = $item['description'] ?? 'Producto incorrecto';
                    if (empty($desc)) $desc = 'Línea importada'; // Fallback
                    
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
                        'unidad' => 'Ud', // Default
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
            
            // Redirect DIRECTLY to Edit Page
            return redirect()->route('filament.admin.resources.albaran-compras.edit', ['record' => $record]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OCR Creation Error: ' . $e->getMessage());
            
            \Filament\Notifications\Notification::make()
                ->title('Error Crítico')
                ->body('No se pudo crear el documento: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            
            // Re-throw to make debugging easier in development? No, handle gracefully.
        }
    }

    public function render()
    {
        return view('livewire.ocr-import-modal');
    }

    private function optimizeAndRefactorImage($sourcePath, $targetRelativePath)
    {
        // Ensure directory exists
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        
        // Source Image
        $sourceFullPath = $disk->path($sourcePath);
        
        // Load image based on type
        // Use try-catch to avoid breaking flow if image is invalid
        try {
            if (!file_exists($sourceFullPath)) {
                 $disk->copy($sourcePath, $targetRelativePath);
                 return;
            }

            $info = getimagesize($sourceFullPath);
            if (!$info) {
                 $disk->copy($sourcePath, $targetRelativePath);
                 return;
            }
            
            $mime = $info['mime'];
            
            $image = null;
            switch ($mime) {
                case 'image/jpeg': $image = imagecreatefromjpeg($sourceFullPath); break;
                case 'image/png': $image = imagecreatefrompng($sourceFullPath); break;
                case 'image/gif': $image = imagecreatefromgif($sourceFullPath); break;
                case 'image/webp': $image = imagecreatefromwebp($sourceFullPath); break;
            }

            if (!$image) {
                // Fallback if not supported or error: just copy
                $disk->copy($sourcePath, $targetRelativePath);
                return;
            }

            // Calculate new dimensions (Max width 1024)
            $maxWidth = 1024;
            $width = imagesx($image);
            $height = imagesy($image);
            
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = (int) floor($height * ($maxWidth / $width));
                
                $tmp = imagecreatetruecolor($newWidth, $newHeight);
                
                // Handle transparency
                if (in_array($mime, ['image/png', 'image/webp'])) {
                    imagealphablending($tmp, false);
                    imagesavealpha($tmp, true);
                }
                
                imagecopyresampled($tmp, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $tmp;
            }

            $targetFullPath = $disk->path($targetRelativePath);
            if (!file_exists(dirname($targetFullPath))) {
                mkdir(dirname($targetFullPath), 0755, true);
            }
            
            // Save logic
            switch ($mime) {
                case 'image/png': imagepng($image, $targetFullPath, 8); break; // 0-9
                case 'image/jpeg': imagejpeg($image, $targetFullPath, 80); break; // 0-100
                case 'image/webp': imagewebp($image, $targetFullPath, 80); break;
                default: imagejpeg($image, $targetFullPath, 80);
            }
            
            imagedestroy($image);
            
        } catch (\Exception $e) {
             \Illuminate\Support\Facades\Log::warning("Image optimization failed: " . $e->getMessage());
             // Fallback
             $disk->copy($sourcePath, $targetRelativePath);
        }
    }
}
