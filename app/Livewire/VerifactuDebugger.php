<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Documento;
use App\Models\Ticket;
use App\Services\VerifactuService;
use App\Services\VerifactuXmlService;
use App\Services\AeatService;
use Illuminate\Support\Facades\Log;

class VerifactuDebugger extends Component
{
    public $recordId;
    public $modelClass;
    public $steps = [];
    public $requestXml;
    public $responseXml;
    public $status = 'idle'; // idle, running, success, error
    public $errorMessage;
    public $isAnulacion = false;

    public function mount($recordId, $modelClass)
    {
        $this->recordId = $recordId;
        $this->modelClass = $modelClass;
        
        $record = $this->getRecord();
        $this->isAnulacion = ($record instanceof Documento && $record->estado === 'anulado') 
                          || ($record instanceof Ticket && $record->status === 'cancelled');
        
        $this->addStep('Preparado para iniciar la depuración del ' . ($this->isAnulacion ? 'anulación' : 'envío') . '.', 'info');
    }

    protected function getRecord()
    {
        return $this->modelClass::findOrFail($this->recordId);
    }

    protected function addStep($message, $type = 'pending')
    {
        $this->steps[] = [
            'message' => $message,
            'type' => $type,
            'time' => now()->format('H:i:s'),
        ];
    }

    public function start()
    {
        $this->status = 'running';
        $this->steps = [];
        $this->requestXml = null;
        $this->responseXml = null;
        $this->errorMessage = null;

        try {
            $record = $this->getRecord();

            // Paso 1: Huella y Encadenamiento
            $this->addStep('Paso 1: Generando huella digital y encadenamiento...', 'running');
            $verifactuService = app(VerifactuService::class);
            
            if (empty($record->verifactu_huella)) {
                $verifactuService->procesarEncadenamiento($record);
                $this->addStep('Huella generada correctamente: ' . substr($record->verifactu_huella, 0, 15) . '...', 'success');
            } else {
                $this->addStep('Usando huella existente: ' . substr($record->verifactu_huella, 0, 15) . '...', 'success');
            }

            // Paso 2: Generar XML
            $this->addStep('Paso 2: Construyendo el payload XML para la AEAT...', 'running');
            $xmlBuilder = app(VerifactuXmlService::class);
            
            if ($this->isAnulacion) {
                $this->requestXml = $xmlBuilder->generateAnulacionXml($record);
            } else {
                $this->requestXml = $xmlBuilder->generateAltaXml($record);
            }
            $this->addStep('XML generado con éxito (' . strlen($this->requestXml) . ' bytes).', 'success');

            // Paso 3: Envío a AEAT
            $this->addStep('Paso 3: Conectando con el Endpoint de la AEAT (SOAP)...', 'running');
            $aeatService = app(AeatService::class);
            
            // Usamos una versión local de submitAlta para capturar la respuesta aunque sea error
            $res = $aeatService->submitAlta($this->requestXml);
            
            $this->responseXml = $res['data'] ?? ($res['raw_body'] ?? null);
            
            if ($res['success']) {
                $this->addStep('Conexión exitosa. La AEAT ha aceptado el registro.', 'success');
                $this->status = 'success';
                
                // Actualizar registro
                $status = $this->isAnulacion ? 'Anulacion_Aceptada' : 'Aceptado';
                $record->update([
                    'verifactu_status' => $status,
                    'verifactu_aeat_id' => $res['trace_id'] ?? 'OK'
                ]);
            } else {
                $this->status = 'error';
                $this->errorMessage = $res['error'] ?? 'Error desconocido en la comunicación.';
                $this->addStep('Error en la comunicación o rechazo de la AEAT.', 'error');
                
                // Actualizar registro con el error
                $record->update([
                    'verifactu_status' => 'error',
                    'verifactu_signature' => substr($this->errorMessage, 0, 1000)
                ]);
            }

        } catch (\Exception $e) {
            $this->status = 'error';
            $this->errorMessage = $e->getMessage();
            $this->addStep('Fallo crítico interno: ' . $this->errorMessage, 'error');
            Log::error("Verifactu Debugger Error: " . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.verifactu-debugger');
    }
}
