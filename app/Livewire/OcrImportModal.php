<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Cache;

class OcrImportModal extends Component
{
    use WithFileUploads;

    public $file;
    public $rawText = '';
    public $isProcessing = false;
    public $parsedData = [
        'date' => null,
        'total' => null,
        'nif' => null,
        'supplier' => null,
    ];

    public function updatedFile()
    {
        $this->processImage();
    }

    public function processImage()
    {
        if (!$this->file) {
            return;
        }

        $this->isProcessing = true;

        try {
            $path = $this->file->getRealPath();
            
            // Run Tesseract
            $ocr = new TesseractOCR($path);
            // $ocr->lang('spa'); // Optional: specify language if installed
            $this->rawText = $ocr->run();

            $this->parseText($this->rawText);

        } catch (\Exception $e) {
            $this->addError('file', 'Error procesando la imagen: ' . $e->getMessage());
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
        
        // NIF/CIF (Simple Spanish NIF regex: 8 digits + letter or Letter + 8 digits)
        if (preg_match('/([A-Z]\d{8}|\d{8}[A-Z])/', $text, $matches)) {
            $this->parsedData['nif'] = $matches[1];
            
            // Try to find provider
            $provider = \App\Models\Tercero::where('nif', $this->parsedData['nif'])->first();
            if ($provider) {
                $this->parsedData['supplier'] = $provider->nombre;
                $this->parsedData['matched_provider_id'] = $provider->id;
            }
        }
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
            'items' => [], // No line items extracted yet
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
