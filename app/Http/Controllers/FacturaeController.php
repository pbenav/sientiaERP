<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Services\FacturaeXmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FacturaeController extends Controller
{
    protected $facturaeService;

    public function __construct(FacturaeXmlService $facturaeService)
    {
        $this->facturaeService = $facturaeService;
    }

    /**
     * Descargar el XML de Facturae para una factura.
     */
    public function download(Documento $record)
    {
        if (!in_array($record->tipo, ['factura', 'factura_compra'])) {
            abort(404, 'Facturae solo disponible para facturas.');
        }

        try {
            $xmlString = $this->facturaeService->generateXml($record);
            
            $filename = 'Facturae_' . str_replace('/', '_', $record->numero ?? $record->id) . '.xml';
            
            return Response::make($xmlString, 200, [
                'Content-Type' => 'text/xml',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Error generating Facturae: ' . $e->getMessage());
        }
    }
}
