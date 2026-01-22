<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Tercero;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiDocumentParserService
{
    public function extractFromImage(string $imagePath): array
    {
        $provider = Setting::get('ai_provider', 'gemini');
        $backupProvider = Setting::get('ai_backup_provider', 'none');
        
        try {
            if ($provider === 'google_doc_ai') {
                return $this->extractWithGoogleCloudDocAI($imagePath);
            }
            
            if ($provider === 'gemini') {
                return $this->extractWithGemini($imagePath);
            }
            
            if ($provider === 'openai') {
                return $this->extractWithOpenAi($imagePath);
            }
            
            throw new \Exception("Proveedor de IA principal ($provider) no configurado o no soportado.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Fallo proveedor principal ($provider): " . $e->getMessage());
            
            // Backup Strategy
            if ($backupProvider === 'tesseract') {
                return $this->extractWithTesseract($imagePath);
            }
            
            throw $e; // Re-throw if no backup configured or both failed
        }
    }

    protected function extractWithGoogleCloudDocAI(string $imagePath): array
    {
        $projectId = Setting::get('google_project_id');
        $location = Setting::get('google_location', 'eu');
        $processorId = Setting::get('google_processor_id');
        $jsonCredentials = Setting::get('google_application_credentials');

        if (empty($projectId) || empty($processorId) || empty($jsonCredentials)) {
             throw new \Exception("Faltan credenciales de Google Cloud Doc AI en Ajustes.");
        }
        
        try {
            $clientOptions = ['credentials' => json_decode($jsonCredentials, true)];
            $client = new \Google\Cloud\DocumentAI\V1\DocumentProcessorServiceClient($clientOptions);
            
            $name = $client->processorName($projectId, $location, $processorId);

            // Read image
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);

            $rawDocument = new \Google\Cloud\DocumentAI\V1\RawDocument();
            $rawDocument->setContent($imageData);
            $rawDocument->setMimeType($mimeType); // e.g. 'image/jpeg' or 'application/pdf'

            $processOptions = new \Google\Cloud\DocumentAI\V1\ProcessOptions(); 
            // Depending on library version, might pass options differently. keeping simple for now.

            $response = $client->processDocument($name, [
                'rawDocument' => $rawDocument,
                // 'fieldMask' => ... for specific fields if needed
            ]);

            $document = $response->getDocument();
            
            // Extract from Entities (Invoice Processor returns entities like supplier_name, etc.)
            $data = [
                'provider_name' => null,
                'provider_nif' => null,
                'document_date' => null,
                'document_number' => null,
                'items' => [],
            ];

            foreach ($document->getEntities() as $entity) {
                $type = $entity->getType();
                $value = $entity->getMentionText() ?? $entity->getNormalizedValue()?->getText(); // normalized preferred for dates

                switch ($type) {
                    case 'supplier_name':
                        $data['provider_name'] = $value;
                        break;
                    case 'supplier_tax_id':
                        $data['provider_nif'] = $value;
                        break;
                    case 'invoice_date':
                        // Normalize date? DocAI usually gives ISO in normalized value
                         $data['document_date'] = $entity->getNormalizedValue()?->getDateValue() 
                            ? sprintf('%04d-%02d-%02d', 
                                $entity->getNormalizedValue()->getDateValue()->getYear(),
                                $entity->getNormalizedValue()->getDateValue()->getMonth(),
                                $entity->getNormalizedValue()->getDateValue()->getDay())
                            : $value;
                        break;
                    case 'invoice_id':
                        $data['document_number'] = $value;
                        break;
                    case 'line_item':
                        // Nested entities for line items
                        $line = ['description' => '', 'quantity' => 1, 'unit_price' => 0];
                        foreach ($entity->getProperties() as $prop) {
                            $propType = $prop->getType();
                            $propValue = $prop->getMentionText() ?? $prop->getNormalizedValue()?->getText();
                            
                            if ($propType === 'line_item/description') $line['description'] = $propValue;
                            if ($propType === 'line_item/quantity') $line['quantity'] = (float) filter_var($propValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                            if ($propType === 'line_item/unit_price') $line['unit_price'] = (float) filter_var($propValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                             // line_item/amount is strict total
                        }
                        if (!empty($line['description'])) {
                            $data['items'][] = $line;
                        }
                        break;
                }
            }

            return $this->enrichData($data);

        } catch (\Exception $e) {
            Log::error("Google Cloud DocAI Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function extractWithTesseract(string $imagePath): array
    {
        $tesseractPath = Setting::get('tesseract_path', '/usr/bin/tesseract');
        
        try {
            $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($imagePath);
            $ocr->executable($tesseractPath);
            // $ocr->lang('spa', 'eng'); // Optional: check if language packs installed
            $text = $ocr->run();
            
            // Regex Parsing (Naive fallback)
            $data = [
                'provider_name' => null, // Hard to extract name via regex reliably without database
                'provider_nif' => null,
                'document_date' => null,
                'document_number' => null,
                'items' => [], // Impossible to extract grid lines reliably with simple regex
            ];

            // 1. Find NIF (Spanish format approx)
            if (preg_match('/[A-Z]\d{8}|\d{8}[A-Z]/', $text, $matches)) {
                $data['provider_nif'] = $matches[0];
            }

            // 2. Find Date (dd/mm/yyyy or yyyy-mm-dd)
            if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/', $text, $matches)) {
                $data['document_date'] = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            // 3. Document Number (look for "Factura", "Albarán" followed by numbers)
            if (preg_match('/(Albarán|Factura|Nº)\s*[:\.]?\s*([A-Z0-9\-\/]+)/i', $text, $matches)) {
                $data['document_number'] = $matches[2];
            }
            
            // Add raw text for debugging or manual entry notice
             $data['observaciones'] = "Extraído vía Tesseract (OCR simple). Verifique.";

            return $this->enrichData($data);

        } catch (\Exception $e) {
            Log::error("Tesseract Error: " . $e->getMessage());
            throw new \Exception("Fallo en Tesseract Backup: " . $e->getMessage());
        }
    }

    protected function extractWithGemini(string $imagePath): array
    {
        // ... (existing code)
        $apiKey = Setting::get('ai_gemini_api_key');
        if (empty($apiKey)) {
            throw new \Exception("API Key de Gemini no configurada en Ajustes.");
        }

        // Initialize dynamic client
        $client = \Gemini::factory()
            ->withApiKey($apiKey)
            ->withBaseUrl(config('gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent')
            ->make();

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $prompt = <<<EOT
Extract the following information from the delivery note (Albarán) image and return it as a strictly valid JSON object. 
If a field is not found, use null.
Fields:
- provider_name: (string)
- provider_nif: (string, if visible)
- document_date: (string YYYY-MM-DD)
- document_number: (string)
- items: (array of objects)
  - description: (string)
  - reference: (string)
  - quantity: (number)
  - unit_price: (number)

IMPORTANT: Return ONLY the JSON object. Do not wrap it in markdown code blocks like ```json ... ```.
EOT;

        try {
            $result = $client->geminiProVision()->generateContent([
                $prompt,
                new \Gemini\Data\Blob(
                    mimeType: $mimeType,
                    data: $imageData
                )
            ]);

            $jsonRaw = $result->text();
            $jsonRaw = str_replace(['```json', '```'], '', $jsonRaw);
            $data = json_decode($jsonRaw, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                 Log::error("Gemini JSON Parse Error: " . json_last_error_msg() . " | Raw: " . $jsonRaw);
                 throw new \Exception("Error al leer la respuesta de Gemini.");
            }

            return $this->enrichData($data ?? []);

        } catch (\Exception $e) {
            Log::error("Gemini Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function extractWithOpenAi(string $imagePath): array
    {
        $apiKey = Setting::get('ai_openai_api_key');
         if (empty($apiKey)) {
            throw new \Exception("API Key de OpenAI no configurada en Ajustes.");
        }
        throw new \Exception("Implementación OpenAI pendiente.");
    }

    protected function enrichData(array $data): array
    {
        // 1. Match Provider
        $providerName = $data['provider_name'] ?? '';
        $providerNif = $data['provider_nif'] ?? '';
        
        $provider = $this->matchProvider($providerName, $providerNif);
        $data['matched_provider_id'] = $provider?->id;
        $data['found_provider'] = (bool)$provider;
        
        // Ensure items array exists
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data['items'] = [];
        }

        // 2. Match Items
        foreach ($data['items'] as &$item) {
            $product = $this->matchProduct($item);
            $item['matched_product_id'] = $product?->id;
            $item['found_product'] = (bool)$product;
            if ($product) {
                $item['system_sku'] = $product->sku;
                $item['system_name'] = $product->name;
                // If price is missing from doc, use system price? No, better use doc price 0 if missing.
            }
        }

        return $data;
    }

    protected function matchProvider(string $name, string $nif): ?Tercero
    {
        // ... (logic remains valid, hiding for brevity during refactor)
        // Restoring logic hidden in previous step
        if (!empty($nif)) {
            $tercero = Tercero::where('nif_cif', $nif)->first();
            if ($tercero) return $tercero;
        }

        if (!empty($name)) {
            return Tercero::where('nombre_comercial', 'LIKE', "%{$name}%")
                ->orWhere('razon_social', 'LIKE', "%{$name}%")
                ->first();
        }
        
        return null;
    }

    protected function matchProduct(array $item): ?Product
    {
         // Restoring logic
        $ref = $item['reference'] ?? '';
        $desc = $item['description'] ?? '';

        if (!empty($ref)) {
            $prod = Product::where('sku', $ref)->orWhere('barcode', $ref)->first();
            if ($prod) return $prod;
        }

        if (!empty($desc)) {
             $prod = Product::where('name', 'LIKE', "%{$desc}%")->first();
             if ($prod) return $prod;
        }

        return null;
    }
}
