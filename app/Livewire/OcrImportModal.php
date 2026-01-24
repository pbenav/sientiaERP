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
            
            $this->addError('data.documento', $e->getMessage());
        }

        $this->isProcessing = false;
    }

    // parseText y extractItems eliminados ya que la lógica está ahora en el servicio


    // extractItems removido

    public function resetState()
    {
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

    public function confirm()
    {
        // 1. Debug Start
        \Filament\Notifications\Notification::make()
            ->title('Procesando...')
            ->body('Iniciando transferencia de datos')
            ->info()
            ->send();

        // Store data in cache to retrieve it in the Create page
        $key = 'albaran_import_' . auth()->id();
        
        // Identify the final path of the image
        $finalPath = null;
        try {
            // Get the current temporary path (resolved in processImage essentially)
            $state = $this->data['documento'] ?? null;
            $currentPath = null;
             if (is_array($state)) {
                $currentPath = array_values($state)[0] ?? null;
            } else {
                $currentPath = $state;
            }

            if ($currentPath) {
                // If it's a temp file managed by Filament/Livewire, we should copy it to a permanent location
                // The $currentPath is usually relative to disk root (public or local)
                // We want to store it in public/documentos/images
                
                $sourceDisk = 'public'; // Assuming upload disk is public as configured
                // Verify existence
                if (!\Illuminate\Support\Facades\Storage::disk($sourceDisk)->exists($currentPath)) {
                    // Try to find it if it was local/temp
                    // But easier: rely on $this->rawText generation which already found the file.
                    // Let's just blindly try to copy if we can find it.
                    // Simplest: If processImage worked, we know where it is? No, processImage logic was local.
                    // Re-resolve using same logic or just use the Storage copy if possible.
                }

                $extension = pathinfo($currentPath, PATHINFO_EXTENSION);
                $newFilename = 'albaran_' . time() . '_' . uniqid() . '.' . $extension;
                $targetDir = 'documentos/images';
                $targetPath = $targetDir . '/' . $newFilename;

                // Move/Copy
                \Illuminate\Support\Facades\Storage::disk('public')->copy($currentPath, $targetPath);
                $finalPath = $targetPath;
                
                \Illuminate\Support\Facades\Log::info('File stored permanently', ['path' => $finalPath]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error moving file: ' . $e->getMessage());
        }

        $data = [
            'raw_text' => $this->rawText,
            'document_date' => $this->parsedData['date'],
            'document_number' => $this->parsedData['document_number'] ?? null,
            'import_total' => $this->parsedData['total'], 
            'provider_nif' => $this->parsedData['nif'],
            'matched_provider_id' => $this->parsedData['matched_provider_id'] ?? null,
            'provider_name' => $this->parsedData['supplier'] ?? null,
            'found_provider' => isset($this->parsedData['matched_provider_id']),
            'items' => $this->parsedData['items'] ?? [],
            'document_image_path' => $finalPath,
        ];

        \Illuminate\Support\Facades\Log::info('OCR Confirm: Storing session', ['data' => $data]);
        
        session()->put('albaran_import_data', $data);
        
        \Illuminate\Support\Facades\Log::info('OCR Confirm: Session stored. Redirecting...');

        // 2. Debug Saved
        \Filament\Notifications\Notification::make()
            ->title('Datos Guardados')
            ->body('Caché escrita correctamente via ' . config('cache.default'))
            ->success()
            ->send();
            
        // Redirect using Filament structure
        return redirect()->to(\App\Filament\Resources\AlbaranCompraResource::getUrl('create'));
    }

    public function render()
    {
        return view('livewire.ocr-import-modal');
    }
}
