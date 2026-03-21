<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceService
{
    private string $certPath;
    private string $certPassword;
    private string $endpoint;

    public function __construct()
    {
        $storedPath = Setting::get('verifactu_cert_path');
        
        if ($storedPath && Storage::disk('local')->exists($storedPath)) {
            $this->certPath = Storage::disk('local')->path($storedPath);
        } else {
            $this->certPath = config('verifactu.cert_path', storage_path('app/certificates/verifactu.p12'));
        }

        $this->certPassword = Setting::get('verifactu_cert_password', '');
        
        $mode = Setting::get('facturae_mode', config('facturae.mode', 'test'));
        if ($mode === 'production') {
            $this->endpoint = Setting::get('facturae_endpoint_production', config('facturae.endpoints.production'));
        } else {
            $this->endpoint = Setting::get('facturae_endpoint_test', config('facturae.endpoints.test'));
        }
    }

    /**
     * Enviar factura a FACe a través de su Web Service.
     */
    public function enviarFactura(Documento $record): array
    {
        try {
            $xmlGenerator = app(FacturaeXmlService::class);
            $xmlContent = $xmlGenerator->generateXml($record);
            
            $filename = 'Facturae_' . str_replace('/', '_', $record->numero ?? $record->id) . '.xml';
            $base64Xml = base64_encode($xmlContent);

            $soapBody = <<<XML
<fac:enviarFactura xmlns:fac="https://face.gob.es/facturasspp">
   <fac:factura>
      <fac:factura>$base64Xml</fac:factura>
      <fac:nombre>$filename</fac:nombre>
      <fac:mime>text/xml</fac:mime>
   </fac:factura>
</fac:enviarFactura>
XML;

            $options = ['verify' => true];
            
            if (str_ends_with(strtolower($this->certPath), '.p12') || str_ends_with(strtolower($this->certPath), '.pfx')) {
                $options['curl'] = [
                    CURLOPT_SSLCERT => $this->certPath,
                    CURLOPT_SSLCERTPASSWD => $this->certPassword,
                    CURLOPT_SSLCERTTYPE => 'P12',
                ];
            } else {
                $options['cert'] = [$this->certPath, $this->certPassword];
            }

            $response = Http::withOptions($options)
                ->withBody($this->wrapInSoapEnvelope($soapBody), 'text/xml; charset=utf-8')
                ->post($this->endpoint);

            if ($response->successful()) {
                $result = $this->parseFaceResponse($response->body());
                
                if ($result['codigo'] === '0') {
                    // Éxito: Guardar registro de FACe
                    $record->update([
                        'facturae_face_id' => $result['numeroRegistro'],
                        'facturae_status' => 'submitted'
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => "Factura enviada correctamente. Registro: " . $result['numeroRegistro'],
                        'data' => $result
                    ];
                }

                return [
                    'success' => false,
                    'error' => "FACe Error ({$result['codigo']}): " . $result['descripcion']
                ];
            }

            return [
                'success' => false,
                'error' => "Error de conexión con FACe ({$response->status()}): " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Face Submission Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function wrapInSoapEnvelope(string $body): string
    {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:fac="https://face.gob.es/facturasspp">
   <soapenv:Header/>
   <soapenv:Body>
      $body
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    protected function parseFaceResponse(string $xmlResponse): array
    {
        // Limpiar namespaces para parsear fácil
        $cleanXml = str_replace(['ns2:', 'soap:', 'env:'], '', $xmlResponse);
        $xml = simplexml_load_string($cleanXml);
        
        // El resultado suele estar en Body -> enviarFacturaResponse -> resultado
        $res = $xml->Body->enviarFacturaResponse->resultado;
        
        return [
            'codigo' => (string)($res->codigo ?? '-1'),
            'descripcion' => (string)($res->descripcion ?? 'Unknown error'),
            'numeroRegistro' => (string)($res->registro->numeroRegistro ?? ''),
            'fechaRecepcion' => (string)($res->registro->fechaRecepcion ?? ''),
        ];
    }
}
