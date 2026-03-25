<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AeatService
{
    private string $certPath;
    private string $certPassword;
    private string $altaEndpoint;
    private string $consultaEndpoint;
    private CertificateService $certService;

    public function __construct(CertificateService $certService)
    {
        $this->certService = $certService;
        $storedPath = \App\Models\Setting::get('verifactu_cert_path');
        
        if ($storedPath) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($storedPath)) {
                $this->certPath = \Illuminate\Support\Facades\Storage::disk('local')->path($storedPath);
            } else {
                // Fallback a storage_path relativo a app/private para compatibilidad manual
                $this->certPath = storage_path('app/private/' . $storedPath);
            }
        } else {
            // Default path (config or manual)
            $this->certPath = config('verifactu.cert_path', storage_path('app/private/certs/verifactu.p12'));
        }

        $this->certPassword = \App\Models\Setting::get('verifactu_cert_password', config('verifactu.cert_password', ''));
        
        $mode = \App\Models\Setting::get('verifactu_mode', config('verifactu.mode', 'test'));
        if ($mode === 'production') {
            $this->altaEndpoint = \App\Models\Setting::get('verifactu_endpoint_production', config('verifactu.endpoints.production'));
            $this->consultaEndpoint = \App\Models\Setting::get('verifactu_endpoint_production_query', config('verifactu.endpoints.production_query'));
        } else {
            $this->altaEndpoint = \App\Models\Setting::get('verifactu_endpoint_test', config('verifactu.endpoints.test'));
            $this->consultaEndpoint = \App\Models\Setting::get('verifactu_endpoint_test_query', config('verifactu.endpoints.test_query'));
        }
    }

    /**
     * Enviar el XML a la AEAT utilizando el certificado digital.
     */
    public function submitAlta(string $xmlContent): array
    {
        $tempCert = null;
        $tempKey = null;

        try {
            // Cargar componentes del certificado (Soporta Legacy a través de CertificateService)
            $certs = $this->certService->loadP12($this->certPath, $this->certPassword);
            $tempCert = $this->certService->createTempPem($certs['cert']);
            $tempKey = $this->certService->createTempPem($certs['pkey']);

            // Veri*Factu usa SOAP 1.1 o 1.2 sobre HTTPS con certificado de cliente
            $options = [
                'verify' => true,
                'curl'   => [
                    CURLOPT_SSLCERT => $tempCert,
                    CURLOPT_SSLKEY  => $tempKey,
                ]
            ];

            $soapBody = $this->wrapInSoapEnvelope($xmlContent);
            Log::info("VERIFACTU SOAP PAYLOAD:");
            Log::info($soapBody);
            
            $response = Http::withOptions($options)
                ->withoutVerifying()
                ->withHeaders([
                    'SOAPAction' => '""', 
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'User-Agent' => 'GuzzleHttp/7',
                    'Connection' => 'close',
                ])
                ->withBody($soapBody, 'text/xml; charset=utf-8')
                ->post($this->altaEndpoint);

            Log::debug("Verifactu: Respuesta de AEAT ({$response->status()})\n" . $response->body());

            if ($response->successful()) {
                $body = $response->body();
                
                // Buscar rastro de aceptación real en el XML
                // Estructura habitual: <EstadoRegistro>Aceptado</EstadoRegistro> o <EstadoRegistro>AceptadoConErrores</EstadoRegistro>
                if (stripos($body, 'Aceptado') !== false) {
                    return [
                        'success' => true,
                        'data' => $body,
                        'trace_id' => $this->extractTraceId($body) ?: 'OK'
                    ];
                }

                return [
                    'success' => false,
                    'error' => "AEAT Rechazado (Business Error): " . $this->extractError($body),
                    'raw_body' => $body
                ];
            }

            return [
                'success' => false,
                'error' => "AEAT Error de Red ({$response->status()}): " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Verifactu Submission Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            if ($tempCert && file_exists($tempCert)) @unlink($tempCert);
            if ($tempKey && file_exists($tempKey)) @unlink($tempKey);
        }
    }

    /**
     * Enviar una consulta de facturas a la AEAT.
     */
    public function submitConsulta(string $xmlContent): array
    {
        $tempCert = null;
        $tempKey = null;

        try {
            // Cargar componentes del certificado
            $certs = $this->certService->loadP12($this->certPath, $this->certPassword);
            $tempCert = $this->certService->createTempPem($certs['cert']);
            $tempKey = $this->certService->createTempPem($certs['pkey']);

            $options = [
                'verify' => true,
                'curl'   => [
                    CURLOPT_SSLCERT => $tempCert,
                    CURLOPT_SSLKEY  => $tempKey,
                ]
            ];

            $response = Http::withOptions($options)
                ->withBody($this->wrapInSoapEnvelope($xmlContent), 'text/xml; charset=utf-8')
                ->post($this->consultaEndpoint);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->body()
                ];
            }

            return [
                'success' => false,
                'error' => "AEAT Query Error ({$response->status()}): " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Verifactu Query Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            if ($tempCert && file_exists($tempCert)) @unlink($tempCert);
            if ($tempKey && file_exists($tempKey)) @unlink($tempKey);
        }
    }

    /**
     * Envolver el contenido en un sobre SOAP reglamentario.
     */
    protected function wrapInSoapEnvelope(string $xmlContent): string
    {
        $xmlContent = trim(preg_replace('/^<\?xml[^>]*\?>/i', '', $xmlContent));
        
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>' . $xmlContent . '</soapenv:Body></soapenv:Envelope>';
    }

    protected function extractTraceId(string $soapResponse): ?string
    {
        // Lógica para extraer el CSV o TraceID del XML de respuesta de la AEAT
        if (preg_match('/<CSV>([^<]+)<\/CSV>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function extractError(string $soapResponse): string
    {
        if (preg_match('/<DescripcionErrorRegistro>([^<]+)<\/DescripcionErrorRegistro>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        if (preg_match('/<faultstring>([^<]+)<\/faultstring>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        return "Error no especificado en la respuesta.";
    }
}
