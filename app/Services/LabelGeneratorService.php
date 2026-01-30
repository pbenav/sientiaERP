<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\LabelFormat;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelGeneratorService
{
    protected $generator;

    public function __construct()
    {
        $this->generator = new BarcodeGeneratorPNG();
    }

    /**
     * Generate PDF for a label document.
     */
    public function generatePDF(Documento $etiqueta)
    {
        if ($etiqueta->tipo !== 'etiqueta') {
            throw new \Exception('Document is not a label document.');
        }

        $format = $etiqueta->labelFormat;
        if (!$format) {
            throw new \Exception('Label format not assigned to document.');
        }

        $barcodeType = Setting::get('barcode_type', 'code128');
        $showPrice = Setting::get('show_price_on_label', 'true') === 'true';
        $displayUppercase = Setting::get('display_uppercase', 'false') === 'true';

        $labels = [];
        
        // Calculate starting offset
        $labelsPerRow = $format->labels_per_row;
        $startIndex = (($etiqueta->fila_inicio ?? 1) - 1) * $labelsPerRow + (($etiqueta->columna_inicio ?? 1) - 1);
        
        // Add empty placeholders for the offset
        for ($s = 0; $s < $startIndex; $s++) {
            $labels[] = null;
        }

        foreach ($etiqueta->lineas as $linea) {
            $product = $linea->product;
            $quantity = (int)$linea->cantidad;

            for ($i = 0; $i < $quantity; $i++) {
                $sku = $linea->codigo ?: ($product?->sku ?? $product?->code ?? $product?->barcode ?? '');
                $barcodeText = $product?->barcode ?: $sku;
                
                $labels[] = [
                    'name' => $linea->descripcion,
                    'sku' => $sku,
                    'price' => $product?->price ?? ($linea->precio_unitario ?? 0),
                    'barcode' => $this->generateBarcode($barcodeText, $barcodeType),
                    'barcode_text' => $barcodeText,
                ];
            }
        }

        $pdf = Pdf::loadView('pdf.etiqueta', [
            'etiqueta' => $etiqueta,
            'format' => $format,
            'labels' => $labels,
            'showPrice' => $showPrice,
            'displayUppercase' => $displayUppercase,
        ]);

        // Configure paper
        if ($format->document_width && $format->document_height) {
            $pdf->setPaper([0, 0, $this->mmToPt($format->document_width), $this->mmToPt($format->document_height)]);
        } else {
            $pdf->setPaper('a4');
        }

        return $pdf;
    }

    /**
     * Generate barcode SVG.
     */
    protected function generateBarcode($text, $type)
    {
        if (empty($text)) return null;

        $typeMap = [
            'code128' => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128,
            'code39' => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_39,
            'ean13' => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_EAN_13,
            // QR Not supported by this library, requires another one if needed
        ];

        $barcodeType = $typeMap[strtolower($type)] ?? \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128;

        try {
            $barcodeData = $this->generator->getBarcode($text, $barcodeType);
            return '<img src="data:image/png;base64,' . base64_encode($barcodeData) . '" alt="barcode">';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Barcode generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert mm to points (1pt = 1/72 inch, 1 inch = 25.4 mm)
     */
    protected function mmToPt($mm)
    {
        return $mm * 72 / 25.4;
    }
}
