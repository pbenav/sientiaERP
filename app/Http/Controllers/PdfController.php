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
        
        $widthSetting = \App\Models\Setting::get('pos_printer_width', '80mm');
        $widthPt = ($widthSetting === '58mm') ? 164.4 : 226.77;
        
        $pdf = Pdf::loadView('pdf.ticket_pos', [
            'ticket' => $record,
            'width' => $widthSetting,
        ]);

        $pdf->setPaper([0, 0, $widthPt, 800], 'portrait');

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

    public function ticketPosRaw(Ticket $record)
    {
        $record->load(['tercero', 'items.product', 'user']);
        
        $widthSetting = \App\Models\Setting::get('pos_printer_width', '80mm');
        $chars = ($widthSetting === '58mm') ? 32 : 42;
        
        $content = "";
        $content .= str_pad(\App\Models\Setting::get('pdf_logo_text', 'sienteERP POS'), $chars, " ", STR_PAD_BOTH) . "\n";
        $content .= str_repeat("-", $chars) . "\n";
        
        $content .= "TICKET: " . str_pad($record->numero ?? 'BORRADOR', $chars - 8, " ", STR_PAD_LEFT) . "\n";
        $content .= "Fecha:  " . str_pad($record->created_at->format('d/m/Y H:i'), $chars - 8, " ", STR_PAD_LEFT) . "\n";
        $content .= str_repeat("-", $chars) . "\n";
        
        $content .= str_pad("DESCRIPCION", $chars - 12, " ", STR_PAD_RIGHT) . str_pad("TOTAL", 12, " ", STR_PAD_LEFT) . "\n";
        foreach ($record->items as $item) {
            $name = substr($item->product ? $item->product->name : 'Producto', 0, $chars - 12);
            $total = number_format($item->total, 2, ',', '.') . 'E';
            $content .= str_pad($name, $chars - 12, " ", STR_PAD_RIGHT) . str_pad($total, 12, " ", STR_PAD_LEFT) . "\n";
        }
        
        $content .= str_repeat("-", $chars) . "\n";
        $content .= "SUBTOTAL:" . str_pad(number_format($record->subtotal, 2, ',', '.') . 'E', $chars - 9, " ", STR_PAD_LEFT) . "\n";
        $content .= "TOTAL: " . str_pad(number_format($record->total, 2, ',', '.') . 'E', $chars - 7, " ", STR_PAD_LEFT) . "\n";
        
        $content .= str_repeat("-", $chars) . "\n";
        $content .= str_pad(\App\Models\Setting::get('pdf_footer_text', 'Gracias por su confianza!'), $chars, " ", STR_PAD_BOTH) . "\n";
        
        $filename = 'TKT_' . str_replace('/', '_', $record->numero ?? 'BORRADOR') . '.txt';
        
        return response($content)
            ->withHeaders([
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
    }
}
