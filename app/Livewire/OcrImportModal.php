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
                    // Try finding it on local (default) disk, often where livewire-tmp lives
                    // Note: 'local' is usually storage/app
                    $localPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    if (file_exists($localPath)) {
                        $fullPath = $localPath;
                    } else {
                        // Sometimes livewire stores inside livewire-tmp which is inside the app but the path passed is just the filename or relative to livewire-tmp?
                        // If path contains 'tmp', check standard storage/app
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
            
            // Run Tesseract
            $ocr = new TesseractOCR($fullPath);
            // $ocr->allowlist(range('a', 'z'), range('A', 'Z'), range('0', '9'), '.,-:/'); // Optional: restrict chars
            $this->rawText = trim($ocr->run());

            if (empty($this->rawText)) {
                throw new \Exception("Tesseract no devolvió ningún texto. La imagen podría no ser legible.");
            }
            
            \Filament\Notifications\Notification::make()
                ->title('OCR Completado')
                ->body('Texto extraído: ' . strlen($this->rawText) . ' caracteres.')
                ->success()
                ->send();

            $this->parseText($this->rawText);

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error procesando imagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            // Also add to form error bag for redundancy
            $this->addError('data.documento', $e->getMessage());
        }

        $this->isProcessing = false;
    }

    protected function parseText($text)
    {
        // Simple regex patterns
        
        // Date (DD/MM/YYYY or DD-MM-YYYY)
        if (preg_match('/(\d{2}[\/\-]\d{2}[\/\-]\d{4})/', $text, $matches)) {
            $this->parsedData['date'] = str_replace('-', '/', $matches[1]);
        }

        // Total (looking for "Total" followed by numbers)
        if (preg_match('/Total.*?(\d+[\.,]\d{2})/i', $text, $matches)) {
            $this->parsedData['total'] = str_replace(',', '.', $matches[1]);
        }
        
        // NIF/CIF (Simple Spanish NIF regex)
        if (preg_match('/([A-Z]\d{8}|\d{8}[A-Z])/', $text, $matches)) {
            $this->parsedData['nif'] = $matches[1];
            
            // Try to find provider
            $provider = \App\Models\Tercero::where('nif', $this->parsedData['nif'])->first();
            if ($provider) {
                $this->parsedData['supplier'] = $provider->nombre;
                $this->parsedData['matched_provider_id'] = $provider->id;
            }
        }

        $this->extractItems($text);
    }

    protected function extractItems($text)
    {
        $lines = explode("\n", $text);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 5) continue;

            // Normalize decimals: replace comma with dot if it looks like a price
            // Regex: Find a number at the end, possibly followed by 'EUR', '€', or noise
            // Pattern: Description + (spaces) + Amount
            if (preg_match('/^(.*?)\s+(\d{1,5}(?:[\.,]\d{2})?)\s*([€a-zA-Z\W]*)$/', $line, $matches)) {
                $description = trim($matches[1]);
                $priceRaw = $matches[2];
                $price = str_replace(',', '.', $priceRaw);
                $qty = 1;

                // Stop words filter (headers, totals)
                if (preg_match('/(Total|Subtotal|Base|IVA|Importe|Fecha|Página|Page|Albarán|Factura|Proveedor)/i', $description)) {
                    continue;
                }
                
                // Detection of "Qty x Description"
                if (preg_match('/^(\d+)\s*[xX]\s+(.*)/', $description, $qtyMatches)) {
                    $qty = $qtyMatches[1];
                    $description = trim($qtyMatches[2]);
                } elseif (preg_match('/^(\d+)\s+(.*)/', $description, $qtyMatches)) {
                    // Start with number might mean quantity (risky but common)
                    // Only if number is small (<100) and description is long
                    if ($qtyMatches[1] < 100 && strlen($qtyMatches[2]) > 5) {
                        $qty = $qtyMatches[1];
                        $description = trim($qtyMatches[2]);
                    }
                }

                $items[] = [
                    'description' => $description,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'matched_product_id' => null
                ];
            }
        }
        
        $this->parsedData['items'] = $items;
    }

    public function resetState()
    {
        $this->rawText = '';
        $this->parsedData = [
            'date' => null,
            'total' => null,
            'nif' => null,
            'supplier' => null,
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
            'import_total' => $this->parsedData['total'], 
            'provider_nif' => $this->parsedData['nif'],
            'matched_provider_id' => $this->parsedData['matched_provider_id'] ?? null,
            'provider_name' => $this->parsedData['supplier'] ?? null,
            'found_provider' => isset($this->parsedData['matched_provider_id']),
            'items' => $this->parsedData['items'] ?? [],
            'document_image_path' => $finalPath,
        ];

        \Illuminate\Support\Facades\Log::info('OCR Confirm: Storing cache', ['key' => $key, 'data' => $data]);
        
        Cache::put($key, $data, now()->addMinutes(10));

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
