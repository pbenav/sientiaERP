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
                    ->disk('public')
                    ->directory('imports/albaranes')
                    ->visibility('private')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->processImage($state);
                    })
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function processImage($state)
    {
        $path = null;
        
        // Handle array or single string from FileUpload
        if (is_array($state)) {
            $path = array_values($state)[0] ?? null;
        } else {
            $path = $state;
        }

        if (!$path) {
            return;
        }

        $this->isProcessing = true;

        try {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
            
            // Run Tesseract
            $ocr = new TesseractOCR($fullPath);
            $this->rawText = $ocr->run();

            $this->parseText($this->rawText);

        } catch (\Exception $e) {
            // Notification or error bag
            $this->addError('documento', 'Error procesando la imagen: ' . $e->getMessage());
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
            if (empty($line)) continue;

            // Heuristic: Line ends with a price-like number
            // Matches "Product Desc 10.00" or "Product 10,00" or "2 x Product 20.00"
            if (preg_match('/(.*?)\s+(\d+[\.,]\d{2})\s*$/', $line, $matches)) {
                $description = trim($matches[1]);
                $price = str_replace(',', '.', $matches[2]);
                $qty = 1;

                // Check if description starts with a quantity?
                // E.g. "2 x Product" or "2 Product"
                if (preg_match('/^(\d+)\s*[xX]?\s+(.*)/', $description, $qtyMatches)) {
                    $qty = $qtyMatches[1];
                    $description = trim($qtyMatches[2]);
                }

                // Skip lines that look like totals or dates
                if (stripos($description, 'Total') !== false || stripos($description, 'Fecha') !== false) {
                    continue;
                }
                
                // Matches "Subtotal", "Base", "IVA", etc. -> skip
                if (preg_match('/(subtotal|base|iva|impuesto)/i', $description)) {
                    continue;
                }

                $items[] = [
                    'description' => $description,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'matched_product_id' => null // Could try to lookup product by name here
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
        // Store data in cache to retrieve it in the Create page
        $key = 'albaran_import_' . auth()->id();
        
        $data = [
            'raw_text' => $this->rawText,
            'document_date' => $this->parsedData['date'],
            'import_total' => $this->parsedData['total'], 
            'provider_nif' => $this->parsedData['nif'],
            'matched_provider_id' => $this->parsedData['matched_provider_id'] ?? null,
            'provider_name' => $this->parsedData['supplier'] ?? null,
            'found_provider' => isset($this->parsedData['matched_provider_id']),
            'items' => $this->parsedData['items'] ?? [],
        ];

        Cache::put($key, $data, now()->addMinutes(10));

        // Redirect using Filament structure
        return redirect()->to(\App\Filament\Resources\AlbaranCompraResource::getUrl('create'));
    }

    public function render()
    {
        return view('livewire.ocr-import-modal');
    }
}
