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
        
        // Prioridad a facturae, fallback a verifactu (compatibilidad)
        $storedPath = Setting::get('facturae_cert_path') ?: Setting::get('verifactu_cert_path');
        
        if ($storedPath) {
            if (Storage::disk('local')->exists($storedPath)) {
                $this->certPath = Storage::disk('local')->path($storedPath);
            } else {
                // Fallback a app/private/certs
                $this->certPath = storage_path('app/private/' . $storedPath);
            }
        } else {
            $this->certPath = config('facturae.cert_path', storage_path('app/private/certs/facturae.p12'));
        }

        $this->certPassword = Setting::get('facturae_cert_password') ?: Setting::get('verifactu_cert_password', '');
        
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

            $soapInnerBody = <<<XML
<fac:enviarFactura xmlns:fac="https://face.gob.es/facturasspp">
   <fac:factura>
      <fac:factura>$base64Xml</fac:factura>
      <fac:nombre>$filename</fac:nombre>
      <fac:mime>text/xml</fac:mime>
   </fac:factura>
</fac:enviarFactura>
XML;

            // Cargar certificado
            $certs = $this->certService->loadP12($this->certPath, $this->certPassword);

            // Generar y FIRMAR el sobre SOAP con WS-Security
            $signedSoap = $this->generateSignedSoapEnvelope($soapInnerBody, $certs['cert'], $certs['pkey']);
            
            // Log para inspección profunda
            file_put_contents('/tmp/last_face_soap.xml', $signedSoap);

            $options = ['verify' => true];
            
            // mTLS (Capa de transporte SSL/TLS) - Sigue siendo necesaria en FACe
            $tempCert = $this->certService->createTempPem($certs['cert']);
            $tempKey = $this->certService->createTempPem($certs['pkey']);

            $options['curl'] = [
                CURLOPT_SSLCERT => $tempCert,
                CURLOPT_SSLKEY => $tempKey,
            ];

            $response = Http::withOptions($options)
                ->withHeaders([
                    'SOAPAction' => 'https://face.gob.es/facturasspp#enviarFactura',
                    'User-Agent' => 'SientiaERP/1.0 (Laravel/11; PHP/8.4)',
                    'Accept' => 'text/xml, application/soap+xml, */*',
                ])
                ->withBody($signedSoap, 'text/xml; charset=utf-8')
                ->post($this->endpoint);

            if ($response->successful()) {
                $result = $this->parseFaceResponse($response->body());
                
                if ($result['codigo'] === '0') {
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
                'error' => "Error de conexión con FACe ({$response->status()}): " . $response->body(),
                'raw_body' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Face Submission Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => "Error en envío a FACe: " . $e->getMessage(),
                'raw_body' => $e instanceof \Exception && isset($e->raw_body) ? $e->raw_body : null
            ];
        } finally {
            if ($tempCert && file_exists($tempCert)) @unlink($tempCert);
            if ($tempKey && file_exists($tempKey)) @unlink($tempKey);
        }
    }

    /**
     * Generar un sobre SOAP 1.1 firmado con WS-Security (WSS-X509 1.0).
     */
    protected function generateSignedSoapEnvelope(string $innerBody, string $publicCert, string $privateKey): string
    {
        $nsSoap = 'http://schemas.xmlsoap.org/soap/envelope/';
        $nsWSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $nsWSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $nsDS = 'http://www.w3.org/2000/09/xmldsig#';

        $doc = new \DOMDocument();
        $envelope = $doc->createElementNS($nsSoap, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:fac', 'https://face.gob.es/facturasspp');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', $nsWSU);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', $nsWSSE);
        $doc->appendChild($envelope);

        $header = $doc->createElementNS($nsSoap, 'soapenv:Header');
        $envelope->appendChild($header);

        $body = $doc->createElementNS($nsSoap, 'soapenv:Body');
        $body->setAttributeNS($nsWSU, 'wsu:Id', 'Body-Content');
        $envelope->appendChild($body);

        // Importar el contenido del body
        $innerBodyDoc = new \DOMDocument();
        $innerBodyDoc->loadXML($innerBody);
        $importedBody = $doc->importNode($innerBodyDoc->documentElement, true);
        $body->appendChild($importedBody);

        $body->setIdAttributeNS($nsWSU, 'Id', true);

        // Bloque de Seguridad
        $security = $doc->createElementNS($nsWSSE, 'wsse:Security');
        $header->appendChild($security);

        // BinarySecurityToken (Debe ir antes que la firma, idealmente al principio)
        $token = $doc->createElementNS($nsWSSE, 'wsse:BinarySecurityToken');
        $token->setAttributeNS($nsWSU, 'wsu:Id', 'Cert-Content');
        $token->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $token->setAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
        
        $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $publicCert);
        $token->nodeValue = $cleanCert;
        $security->appendChild($token);
        $token->setIdAttributeNS($nsWSU, 'Id', true);

        // Timestamp
        $timestamp = $doc->createElementNS($nsWSU, 'wsu:Timestamp');
        $timestamp->setAttributeNS($nsWSU, 'wsu:Id', 'TS-Content');
        $created = gmdate("Y-m-d\TH:i:s\Z");
        $expires = gmdate("Y-m-d\TH:i:s\Z", time() + 3600); // 1 hora para evitar lag
        $timestamp->appendChild($doc->createElementNS($nsWSU, 'wsu:Created', $created));
        $timestamp->appendChild($doc->createElementNS($nsWSU, 'wsu:Expires', $expires));
        $security->appendChild($timestamp);
        $timestamp->setIdAttributeNS($nsWSU, 'Id', true);
        
        // FIRMA MANUAL con xmlseclibs para evitar Namespace Error
        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(\RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKey);

        // 1. Canonizar y calcular Digest del Timestamp (SHA-256)
        $c14nTS = $timestamp->C14N(true, false);
        $digestTS = base64_encode(hash('sha256', $c14nTS, true));

        // 2. Canonizar y calcular Digest del Body (SHA-256)
        $c14nBody = $body->C14N(true, false);
        $digestBody = base64_encode(hash('sha256', $c14nBody, true));

        // 3. Construir SignedInfo (SHA-256)
        $signedInfoXml = <<<XML
<ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
  <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
  <ds:Reference URI="#TS-Content">
    <ds:Transforms>
      <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
    </ds:Transforms>
    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
    <ds:DigestValue>$digestTS</ds:DigestValue>
  </ds:Reference>
  <ds:Reference URI="#Body-Content">
    <ds:Transforms>
      <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
    </ds:Transforms>
    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
    <ds:DigestValue>$digestBody</ds:DigestValue>
  </ds:Reference>
</ds:SignedInfo>
XML;

        // 4. Firmar el SignedInfo
        // Para firmar, debemos canonizar el propio SignedInfo
        $siDoc = new \DOMDocument();
        $siDoc->loadXML($signedInfoXml);
        $c14nSI = $siDoc->documentElement->C14N(true, false);
        $signatureValue = base64_encode($objKey->signData($c14nSI));

        // 5. Ensamblar ds:Signature en el bloque Security
        $signatureNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
        $security->appendChild($signatureNode);

        $importedSI = $doc->importNode($siDoc->documentElement, true);
        $signatureNode->appendChild($importedSI);

        $svNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignatureValue', $signatureValue);
        $signatureNode->appendChild($svNode);

        $keyInfo = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:KeyInfo');
        $signatureNode->appendChild($keyInfo);

        $secRef = $doc->createElementNS($nsWSSE, 'wsse:SecurityTokenReference');
        $keyInfo->appendChild($secRef);

        $ref = $doc->createElementNS($nsWSSE, 'wsse:Reference');
        $ref->setAttribute('URI', '#Cert-Content');
        $ref->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $secRef->appendChild($ref);

        return $doc->saveXML();
    }

    protected function parseFaceResponse(string $xmlResponse): array
    {
        // Si la respuesta contiene <html> o no empieza por < (es probablemente un error con ng-cloak)
        if (stripos($xmlResponse, '<html') !== false || !str_starts_with(trim($xmlResponse), '<')) {
            Log::error("Face Response is HTML: " . substr($xmlResponse, 0, 1000));
            $error = new \Exception("La respuesta de FACe es una página HTML en lugar de XML (posible bloqueo por firewall o error de sesión).");
            $error->raw_body = $xmlResponse;
            throw $error;
        }

        // Evitar que libxml genere warnings que Laravel capture como excepciones
        libxml_use_internal_errors(true);
        
        // Limpiar namespaces comunes para facilitar el acceso con SimpleXML
        $cleanXml = str_replace(['ns2:', 'soap:', 'env:', 'SOAP-ENV:', 'S:'], '', $xmlResponse);
        $xml = @simplexml_load_string($cleanXml);
        
        if (!$xml) {
            $libError = libxml_get_last_error();
            Log::error("Face XML Parse Error: " . ($libError ? $libError->message : 'Unknown') . "\nContent: " . substr($cleanXml, 0, 500));
            libxml_clear_errors();
            $error = new \Exception("No se pudo parsear la respuesta XML de FACe.");
            $error->raw_body = $xmlResponse;
            throw $error;
        }

        // Caso de error SOAP (Fault)
        if (isset($xml->Body->Fault)) {
            $code = (string)$xml->Body->Fault->faultcode;
            $msg = (string)$xml->Body->Fault->faultstring;
            return [
                'codigo' => '-1',
                'descripcion' => "$code: $msg",
                'numeroRegistro' => null
            ];
        }
        
        // El resultado suele estar en Body -> enviarFacturaResponse -> resultado
        $res = $xml->Body->enviarFacturaResponse->resultado;
        
        return [
            'codigo' => (string)($res->codigo ?? '-1'),
            'descripcion' => (string)($res->descripcion ?? 'Respuesta desconocida'),
            'numeroRegistro' => (string)($res->numeroRegistro ?? null)
        ];
    }

    /**
     * Consultar el estado de una factura en FACe.
     */
    public function consultarFactura(string $numeroRegistro): array
    {
        $tempCert = null;
        $tempKey = null;

        try {
            $soapInnerBody = <<<XML
<fac:consultarFactura xmlns:fac="https://face.gob.es/facturasspp">
   <fac:numeroRegistro>$numeroRegistro</fac:numeroRegistro>
</fac:consultarFactura>
XML;

            $certs = $this->certService->loadP12($this->certPath, $this->certPassword);
            $signedSoap = $this->generateSignedSoapEnvelope($soapInnerBody, $certs['cert'], $certs['pkey']);

            $options = ['verify' => true];
            $tempCert = $this->certService->createTempPem($certs['cert']);
            $tempKey = $this->certService->createTempPem($certs['pkey']);

            $options['curl'] = [
                CURLOPT_SSLCERT => $tempCert,
                CURLOPT_SSLKEY => $tempKey,
            ];

            $response = Http::withOptions($options)
                ->withHeaders([
                    'SOAPAction' => 'https://face.gob.es/facturasspp#consultarFactura',
                    'Content-Type' => 'text/xml; charset=utf-8',
                ])
                ->withBody($signedSoap, 'text/xml')
                ->post($this->endpoint);

            if ($response->successful()) {
                // Parse simplificado para consulta (idéntico al de envío pero con otros campos)
                $cleanXml = str_replace(['ns2:', 'soap:', 'env:', 'SOAP-ENV:', 'S:'], '', $response->body());
                $xml = @simplexml_load_string($cleanXml);
                
                if (isset($xml->Body->consultarFacturaResponse->resultado)) {
                    $res = $xml->Body->consultarFacturaResponse->resultado;
                    $factura = $xml->Body->consultarFacturaResponse->factura;

                    return [
                        'success' => true,
                        'codigo' => (string)$res->codigo,
                        'descripcion' => (string)$res->descripcion,
                        'estado' => (string)($factura->estado ?? 'Desconocido'),
                        'codigo_estado' => (string)($factura->codigo_estado ?? ''),
                    ];
                }
            }

            return ['success' => false, 'error' => "Error FACe: " . $response->status()];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            if ($tempCert && file_exists($tempCert)) @unlink($tempCert);
            if ($tempKey && file_exists($tempKey)) @unlink($tempKey);
        }
    }
}
