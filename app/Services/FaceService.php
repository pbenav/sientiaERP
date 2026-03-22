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
    private CertificateService $certService;

    public function __construct(CertificateService $certService)
    {
        $this->certService = $certService;
        $storedPath = Setting::get('facturae_cert_path');
        
        if ($storedPath) {
            if (Storage::disk('local')->exists($storedPath)) {
                $this->certPath = Storage::disk('local')->path($storedPath);
            } elseif (Storage::disk('public')->exists($storedPath)) {
                $this->certPath = Storage::disk('public')->path($storedPath);
            } else {
                // Si no existe en ningún disco estándar, probamos trayectoria relativa directa (por compatibilidad)
                $this->certPath = storage_path('app/private/' . $storedPath);
            }
        } else {
            $this->certPath = config('facturae.cert_path', storage_path('app/certificates/facturae.p12'));
        }

        $this->certPassword = Setting::get('facturae_cert_password', '');
        
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
        $tempCert = null;
        $tempKey = null;

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
            
            // Cargar certificado usando el nuevo CertificateService (con fallback legacy)
            $certs = $this->certService->loadP12($this->certPath, $this->certPassword);
            
            // Para cURL/Guzzle, debemos usar archivos PEM temporales
            $tempCert = $this->certService->createTempPem($certs['cert']);
            $tempKey = $this->certService->createTempPem($certs['pkey']);

            $options['curl'] = [
                CURLOPT_SSLCERT => $tempCert,
                CURLOPT_SSLKEY => $tempKey,
                // Ya no necesitamos CURLOPT_SSLCERTTYPE => 'P12' porque ahora es PEM
            ];

            $response = Http::withOptions($options)
                ->withHeaders([
                    'SOAPAction' => 'https://face.gob.es/facturasspp#enviarFactura',
                    'User-Agent' => 'SientiaERP/1.0 (Laravel/11; PHP/8.4)',
                    'Accept' => 'text/xml, application/soap+xml, */*',
                ])
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
                'error' => "Error en envío a FACe: " . $e->getMessage()
            ];
        } finally {
            // Limpiar archivos temporales
            if ($tempCert && file_exists($tempCert)) @unlink($tempCert);
            if ($tempKey && file_exists($tempKey)) @unlink($tempKey);
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
        // Si la respuesta contiene <html> o no empieza por < (es probablemente un error con ng-cloak)
        if (stripos($xmlResponse, '<html') !== false || !str_starts_with(trim($xmlResponse), '<')) {
            Log::error("Face Response is HTML: " . substr($xmlResponse, 0, 1000));
            throw new \Exception("La respuesta de FACe es una página HTML en lugar de XML (posible bloqueo por firewall o error de sesión).");
        }

        // Evitar que libxml genere warnings que Laravel capture como excepciones
        libxml_use_internal_errors(true);
        
        // Limpiar namespaces para parsear fácil
        $cleanXml = str_replace(['ns2:', 'soap:', 'env:'], '', $xmlResponse);
        $xml = @simplexml_load_string($cleanXml);
        
        if (!$xml) {
            $libError = libxml_get_last_error();
            Log::error("Face XML Parse Error: " . ($libError ? $libError->message : 'Unknown') . "\nContent: " . substr($cleanXml, 0, 500));
            libxml_clear_errors();
            throw new \Exception("Error al parsear el XML de respuesta de FACe.");
        }
        
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
