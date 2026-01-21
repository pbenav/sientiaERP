<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function downloadDocumento(Documento $record)
    {
        $record->load(['tercero', 'lineas', 'documentoOrigen', 'formaPago']);
        
        if (in_array($record->tipo, ['recibo', 'recibo_compra'])) {
            $importeLetras = \App\Services\NumeroALetras::convertir($record->total);
            
            $pdf = Pdf::loadView('pdf.recibo', [
                'doc' => $record,
                'importeLetras' => $importeLetras,
            ]);
        } else {
            $pdf = Pdf::loadView('pdf.documento', [
                'doc' => $record,
            ]);
        }

        $filename = strtoupper($record->tipo) . '_' . str_replace('/', '_', $record->numero ?? 'BORRADOR') . '.pdf';

        return $pdf->download($filename);
    }
}
