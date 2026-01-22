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
        
        if ($provider === 'gemini') {
            return $this->extractWithGemini($imagePath);
        }
        
        if ($provider === 'openai') {
            return $this->extractWithOpenAi($imagePath);
        }

        throw new \Exception("Proveedor de IA no configurado o no soportado.");
    }

    protected function extractWithGemini(string $imagePath): array
    {
        $apiKey = Setting::get('ai_gemini_api_key');
        if (empty($apiKey)) {
            throw new \Exception("API Key de Gemini no configurada en Ajustes.");
        }

        // Initialize dynamic client
        $client = \Gemini::factory()
            ->withApiKey($apiKey)
            ->withBaseUrl(config('gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent') // Fallback mostly, factory handles it
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
            // Using gemini-1.5-flash which is multimodal and fast, or gemini-pro-vision
            // The package "google-gemini-php" typically maps "geminiProVision" to the vision model.
            // Let's try to use the generic generateContent with a model name if possible, 
            // or use the specific method. For safety with recent models, we ask for 'gemini-1.5-flash' if available,
            // but the SDK might rely on 'geminiProVision' facade method.
            
            // Using the factory client directly:
            $result = $client->geminiProVision()->generateContent([
                $prompt,
                new \Gemini\Data\Blob(
                    mimeType: $mimeType,
                    data: $imageData
                )
            ]);

            $jsonRaw = $result->text();
            
            // Cleanup markdown if present
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
        
        // Manual configuration override or separate client instance needed for OpenAI 
        // if we want to support dynamic keys without global config change.
        // For now, leaving as placeholder/todo since user prioritized Gemini.
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
