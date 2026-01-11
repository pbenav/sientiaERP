<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function downloadDocumento(Documento $record)
    {
        $record->load(['tercero', 'lineas']);
        
        $pdf = Pdf::loadView('pdf.documento', [
            'doc' => $record,
        ]);

        $filename = strtoupper($record->tipo) . '_' . str_replace('/', '_', $record->numero) . '.pdf';

        return $pdf->download($filename);
    }
}
