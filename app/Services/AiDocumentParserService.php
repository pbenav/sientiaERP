<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Models\Tercero;
use App\Models\Setting;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiDocumentParserService
{
    public function extractFromImage(string $imagePath): array
    {
        // Petición usuario: Usar Tesseract por defecto o fallback silencioso si Google falla.
        // Si no hay configuración explícita, intentamos leer, pero priorizamos Tesseract si Google da error.
        
        $provider = Setting::get('ai_provider', 'gemini'); 
        // Force Tesseract priority if requested by context or config
        // For now, standard flow with robust fallback.

        $result = null;
        $error = null;

        try {
            if ($provider === 'google_doc_ai') {
                $result = $this->extractWithGoogleCloudDocAI($imagePath);
            } elseif ($provider === 'gemini') {
                $result = $this->extractWithGemini($imagePath);
            } elseif ($provider === 'openai') {
                $result = $this->extractWithOpenAi($imagePath);
            } elseif ($provider === 'tesseract') {
                $result = $this->extractWithTesseract($imagePath);
            }
        } catch (\Throwable $e) {
            $error = $e;
            \Illuminate\Support\Facades\Log::warning("Fallo proveedor principal ($provider): " . $e->getMessage());
        }

        if ($result) {
            return $result;
        }

        // BACKUP AUTOMATICO: Tesseract
        // Si falló el principal (ej: google creds error), usamos Tesseract siempre sirve como red de seguridad.
        try {
             return $this->extractWithTesseract($imagePath);
        } catch (\Exception $tesseractError) {
             // Si tesseract también falla, entonces lanzamos el error original si existía, o el de tesseract
             throw $error ?? $tesseractError;
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

        if (!class_exists(\Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient::class)) {
            throw new \Exception("Biblioteca Google Cloud Document AI no encontrada. Por favor, ejecute 'composer install'.");
        }
        
        try {
            // Set regional endpoint based on processor location
            $apiEndpoint = $location === 'eu' 
                ? 'eu-documentai.googleapis.com' 
                : 'us-documentai.googleapis.com';
            
            $clientOptions = [
                'credentials' => json_decode($jsonCredentials, true),
                'apiEndpoint' => $apiEndpoint
            ];
            
            $client = new \Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient($clientOptions);
            
            $name = $client->processorName($projectId, $location, $processorId);

            // Read image
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);

            $rawDocument = new \Google\Cloud\DocumentAI\V1\RawDocument();
            $rawDocument->setContent($imageData);
            $rawDocument->setMimeType($mimeType); // e.g. 'image/jpeg' or 'application/pdf'

            $request = new \Google\Cloud\DocumentAI\V1\ProcessRequest();
            $request->setName($name);
            $request->setRawDocument($rawDocument);

            Log::info('DocAI: Sending request to processor', ['processor_id' => $processorId, 'project' => $projectId]);
            $response = $client->processDocument($request);
            Log::info('DocAI: Received response');

            $document = $response->getDocument();
            
            $entityCount = count($document->getEntities());
            Log::info('DocAI: Total entities found', ['count' => $entityCount]);
            
            // Extract from Entities (Invoice Processor returns entities like supplier_name, etc.)
            $data = [
                'provider_name' => null,
                'provider_nif' => null,
                'document_date' => null,
                'document_number' => null,
                'items' => [],
            ];

            foreach ($document->getEntities() as $entity) {
                $type = Str::snake(strtolower($entity->getType()));
                $value = $entity->getMentionText();
                
                \Illuminate\Support\Facades\Log::debug("DocAI Entity Found", ['type' => $type, 'value' => $value]);

                // Mapping for top-level entities
                if (in_array($type, ['supplier_name', 'nombre_proveedor', 'proveedor', 'vendor_name'])) {
                    $data['provider_name'] = $value;
                } elseif (in_array($type, ['supplier_tax_id', 'nif_proveedor', 'cif_proveedor', 'nif_emisor', 'cif_emisor', 'vendor_tax_id'])) {
                    $data['provider_nif'] = $value;
                } elseif (in_array($type, ['invoice_date', 'fecha_documento', 'fecha_factura', 'fecha_albaran', 'document_date'])) {
                    $data['document_date'] = $entity->getNormalizedValue()?->getDateValue() 
                        ? sprintf('%04d-%02d-%02d', 
                            $entity->getNormalizedValue()->getDateValue()->getYear(),
                            $entity->getNormalizedValue()->getDateValue()->getMonth(),
                            $entity->getNormalizedValue()->getDateValue()->getDay())
                        : $value;
                } elseif (in_array($type, ['invoice_id', 'numero_documento', 'numero_factura', 'numero_albaran', 'document_id', 'document_number'])) {
                    $data['document_number'] = $value;
                } elseif (in_array($type, ['total_amount', 'importe_total', 'total_documento', 'grand_total', 'total'])) {
                     $data['total'] = (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                } elseif (in_array($type, ['line_item', 'linea_articulo', 'partida', 'item'])) {
                    // Hierarchical entities for line items
                    $line = [
                        'description' => '',
                        'quantity' => 1.0,
                        'unit_price' => 0.0,
                        'discount' => 0.0,
                        'reference' => null,
                        'product_code' => null
                    ];
                    
                    foreach ($entity->getProperties() as $prop) {
                        $propType = Str::snake(strtolower($prop->getType()));
                        $propValue = $prop->getMentionText();
                        
                        // Normalize the type (remote parent prefix if any, e.g. line_item/description)
                        $cleanPropType = last(explode('/', $propType));
                        
                        if (in_array($cleanPropType, ['description', 'product_description', 'descripcion', 'nombre_articulo'])) {
                            $line['description'] = $propValue;
                        } elseif (in_array($cleanPropType, ['quantity', 'cantidad', 'unidades', 'qty'])) {
                            $line['quantity'] = (float) filter_var($propValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        } elseif (in_array($cleanPropType, ['unit_price', 'precio_unitario', 'precio_coste', 'unit_cost'])) {
                            $line['unit_price'] = (float) filter_var($propValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        } elseif (in_array($cleanPropType, ['product_code', 'sku', 'reference', 'referencia', 'codigo'])) {
                            $line['product_code'] = $propValue;
                            $line['reference'] = $propValue;
                        } elseif (in_array($cleanPropType, ['amount', 'line_total', 'importe_linea', 'total'])) {
                            $line['line_total'] = (float) filter_var($propValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        }
                    }
                    
                    if (!empty($line['description']) || $line['unit_price'] > 0) {
                        $data['items'][] = $line;
                    }
                }
            }

            Log::info('DocAI: Final extracted data', [
                'provider' => $data['provider_name'],
                'date' => $data['document_date'],
                'items_count' => count($data['items'])
            ]);

            return $this->enrichData($data);

        } catch (\Exception $e) {
            Log::error("Google Cloud DocAI Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function extractWithTesseract(string $imagePath): array
    {
        if (!class_exists(TesseractOCR::class)) {
            throw new \Exception("Biblioteca TesseractOCR no encontrada. Por favor, instale 'thiagoalessio/tesseract_ocr'.");
        }
        $tesseractPath = Setting::get('tesseract_path', '/usr/bin/tesseract');
        
        try {
            $ocr = new TesseractOCR($imagePath);
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
        $apiKey = Setting::get('ai_gemini_api_key', config('services.google.ai_api_key'));
        if (empty($apiKey)) {
            throw new \Exception("API Key de Gemini no configurada en Ajustes ni en .env");
        }

        // Initialize dynamic client
        $client = \Gemini::factory()
            ->withApiKey($apiKey)
            ->make();

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $prompt = <<<EOT
You are a High-Precision Document Extraction AI specialized in Spanish "Albaranes de compra" (Delivery Notes).
Your goal is to transform the provided image into a structured JSON dataset with 100% accuracy.

DOCUMENT ANALYSIS RULES:
1. **Thorough Scan**: Scan the entire document from top to bottom. Do not stop after the first few lines. Many documents have items split across pages or in long tables.
2. **Table Structure**: Identify the item table. Columns may appear in any order (e.g., SKU, Description, Qty, Price, Dto%, Total). Map them correctly regardless of order.
3. **Multi-line Descriptions**: Product descriptions often span 2 or 3 lines. Merge them into a single string for the "description" field.
4. **Data Types**: 
   - "quantity", "unit_price", "discount", and "line_total" MUST be numbers. 
   - Remove currency symbols (€, $) and thousands separators before converting. Use a dot (.) as decimal separator.
5. **Supplier vs Customer**: Ensure "provider_name" is the sender/supplier, not the recipient.

JSON STRUCTURE:
{
  "provider_name": "Supplier legal name or commercial name",
  "provider_nif": "CIF/NIF of the supplier",
  "document_date": "YYYY-MM-DD",
  "document_number": "Invoice or Delivery Note number",
  "items": [
    {
      "description": "Full product name (merge multi-line descriptions)",
      "reference": "Internal SKU or barcode if visible",
      "quantity": 0.00,
      "unit_price": 0.00,
      "discount": 0.00,
      "line_total": 0.00
    }
  ]
}

CRITICAL ITEM EXTRACTION RULES:
- **EXTRACT EVERY LINE**: If it looks like a product line, extract it. Do not skip items.
- **UNIT PRICE**: Extract the COST price from the supplier. Usually labeled as "Precio", "P. Unit", "Coste".
- **DISCOUNT**: Look for columns like "Dto", "Desc", "%". Extract only the percentage value.
- **NO HALLUCINATIONS**: If a value is not present, use null for strings and 0 for numbers.
- **OUTPUT**: Return ONLY the JSON object. Do not include introductory text.
EOT;

        try {
            $modelName = Setting::get('ai_gemini_model', 'gemini-1.5-flash');
            $result = $client->generativeModel($modelName)->generateContent([
                $prompt,
                new \Gemini\Data\Blob(
                    mimeType: \Gemini\Enums\MimeType::from($mimeType),
                    data: $imageData
                )
            ]);


            $jsonRaw = $result->text();
            
            // Log first 1000 chars for debugging
            Log::info('Gemini Raw Response (first 1000 chars)', ['response' => substr($jsonRaw, 0, 1000)]);
            
            // Extracts JSON from markdown block if present
            if (preg_match('/```json\s*(.*?)\s*```/s', $jsonRaw, $matches)) {
                $jsonRaw = $matches[1];
                Log::info('Extracted JSON from markdown block');
            } elseif (preg_match('/```\s*(.*?)\s*```/s', $jsonRaw, $matches)) {
                // Try without json specifier
                $jsonRaw = $matches[1];
                Log::info('Extracted from generic code block');
            } else {
                 // Fallback: cleanup
                 $jsonRaw = str_replace(['```json', '```'], '', $jsonRaw);
                 $jsonRaw = trim($jsonRaw);
            }
            
            try {
                $data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::error('Gemini JSON Parse Error', [
                    'error' => $e->getMessage(),
                    'raw_sample' => substr($jsonRaw, 0, 500)
                ]);
                throw new \Exception("Error parsing Gemini response: " . $e->getMessage());
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
