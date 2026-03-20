<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AeatService
{
    private string $certPath;
    private string $certPassword;
    private string $endpoint;

    public function __construct()
    {
        $this->certPath = \App\Models\Setting::get('verifactu_cert_path', config('verifactu.cert_path', storage_path('app/certificates/verifactu.p12')));
        $this->certPassword = \App\Models\Setting::get('verifactu_cert_password', config('verifactu.cert_password', ''));
        
        $mode = \App\Models\Setting::get('verifactu_mode', config('verifactu.mode', 'test'));
        $this->endpoint = ($mode === 'production') 
            ? config('verifactu.endpoints.production') 
            : config('verifactu.endpoints.test');
    }

    /**
     * Enviar el XML a la AEAT utilizando el certificado digital.
     */
    public function submitAlta(string $xmlContent): array
    {
        try {
            // Veri*Factu usa SOAP 1.1 o 1.2 sobre HTTPS con certificado de cliente
            $response = Http::withOptions([
                'cert' => [$this->getCertPemPath(), $this->certPassword], // Guzzle soporta PEM o P12 directamente si se indica
                'verify' => true,
            ])
            ->withBody($this->wrapInSoapEnvelope($xmlContent), 'text/xml; charset=utf-8')
            ->post($this->endpoint);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->body(),
                    'trace_id' => $this->extractTraceId($response->body())
                ];
            }

            return [
                'success' => false,
                'error' => "AEAT Error ({$response->status()}): " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Verifactu Submission Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envolver el contenido en un sobre SOAP reglamentario.
     */
    protected function wrapInSoapEnvelope(string $xmlContent): string
    {
        // Limpiar declaraciones XML internas del content para evitar conflicto
        $xmlContent = preg_replace('/^<\?xml[^>]*\?>/i', '', $xmlContent);
        
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tic="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
   <soapenv:Header/>
   <soapenv:Body>
      $xmlContent
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    protected function extractTraceId(string $soapResponse): ?string
    {
        // Lógica para extraer el CSV o TraceID del XML de respuesta de la AEAT
        if (preg_match('/<CSV>([^<]+)<\/CSV>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function getCertPemPath(): string
    {
        // En producción, cargaríamos el path real del certificado
        return $this->certPath;
    }
}
