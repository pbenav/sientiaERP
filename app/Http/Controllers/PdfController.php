<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Ticket;
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

        $filename = strtoupper($record->tipo) . '_' . str_replace('/', '_', $record->numero ?? $record->id) . '.pdf';

        return $pdf->download($filename);
    }

    public function ticketDocumento(Documento $record)
    {
        $record->load(['tercero', 'lineas', 'documentoOrigen', 'formaPago']);
        
        // Formato Ticket (80mm ancho, altura variable aproximada)
        $pdf = Pdf::loadView('pdf.ticket', [
            'doc' => $record,
        ]);

        // 80mm = 226.77pt
        // Altura: 150mm es un punto de partida, pero dompdf no soporta "auto" altura total
        // Ponemos uno largo para que no corte y forzamos el alto en la vista si fuese necesario
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        $filename = 'TICKET_' . str_replace('/', '_', $record->numero ?? 'BORRADOR') . '.pdf';

        return $pdf->stream($filename); // Usamos stream para visualización directa
    }

    public function ticketPos(Ticket $record)
    {
        $record->load(['tercero', 'items.product', 'user']);
        
        $pdf = Pdf::loadView('pdf.ticket_pos', [
            'ticket' => $record,
        ]);

        // 80mm = 226.77pt
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        $filename = 'TKT_' . str_replace('/', '_', $record->numero ?? 'BORRADOR') . '.pdf';

        return $pdf->stream($filename);
    }

    public function ticketRegalo(Ticket $record)
    {
        $record->load(['tercero', 'items.product', 'user']);
        
        $pdf = Pdf::loadView('pdf.ticket_regalo', [
            'ticket' => $record,
        ]);

        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        $filename = 'REGALO_' . str_replace('/', '_', $record->numero ?? 'BORRADOR') . '.pdf';

        return $pdf->stream($filename);
    }
}
