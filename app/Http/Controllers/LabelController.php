<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Services\LabelGeneratorService;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    protected $labelService;

    public function __construct(LabelGeneratorService $labelService)
    {
        $this->labelService = $labelService;
    }

    public function download(Documento $record)
    {
        if ($record->tipo !== 'etiqueta') {
            abort(404);
        }

        try {
            $pdf = $this->labelService->generatePDF($record);
            return $pdf->download('etiquetas-' . ($record->numero ?? $record->id) . '.pdf');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
