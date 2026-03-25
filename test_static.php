<?php
require 'vendor/autoload.php';

use App\Models\Setting;
use App\Services\CertificateService;
use Illuminate\Support\Facades\Http;

// Cargar entorno Laravel manual
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$certService = new CertificateService();
$certPath = Setting::get('verifactu_cert_path');
$certPassword = Setting::get('verifactu_cert_password');
$endpoint = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP";

$fullPath = storage_path('app/private/' . $certPath);
$certs = $certService->loadP12($fullPath, $certPassword);
$tempCert = $certService->createTempPem($certs['cert']);
$tempKey = $certService->createTempPem($certs['pkey']);

$soapMessage = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sf="https://www1.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
   <soapenv:Header/>
   <soapenv:Body>
      <sf:RegFactuSistemaFacturacion>
         <sf:Cabecera>
            <sf:IDEmisorFactura><sf:NIF>24265003A</sf:NIF></sf:IDEmisorFactura>
         </sf:Cabecera>
         <sf:RegistroAlta>
            <sf:IDRegistroFacturacion>
               <sf:IDEmisorFactura><sf:NIF>24265003A</sf:NIF></sf:IDEmisorFactura>
               <sf:NumSerieFactura>FAC-TEST-0001</sf:NumSerieFactura>
               <sf:FechaExpedicionFactura>24-03-2026</sf:FechaExpedicionFactura>
            </sf:IDRegistroFacturacion>
            <sf:NombreRazonEmisor>BENAVIDES ORTIGOSA PABLO ANTONIO</sf:NombreRazonEmisor>
            <sf:TipoFactura>F1</sf:TipoFactura>
            <sf:ConceptoFactura>Test</sf:ConceptoFactura>
            <sf:DesgloseIVA>
               <sf:DetalleIVA>
                  <sf:BaseImponible>100.00</sf:BaseImponible>
                  <sf:TipoImpositivo>21.00</sf:TipoImpositivo>
                  <sf:CuotaRepercutida>21.00</sf:CuotaRepercutida>
               </sf:DetalleIVA>
            </sf:DesgloseIVA>
            <sf:ImporteTotal>121.00</sf:ImporteTotal>
            <sf:Huella>0000000000000000000000000000000000000000000000000000000000000000</sf:Huella>
            <sf:SistemaInformatico>
               <sf:NombreSistema>TEST</sf:NombreSistema>
               <sf:Version>1.0</sf:Version>
               <sf:NIFEntidadDesarrolladora>24265003A</sf:NIFEntidadDesarrolladora>
               <sf:TipoUso>01</sf:TipoUso>
            </sf:SistemaInformatico>
         </sf:RegistroAlta>
      </sf:RegFactuSistemaFacturacion>
   </soapenv:Body>
</soapenv:Envelope>
XML;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soapMessage);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSLCERT, $tempCert);
curl_setopt($ch, CURLOPT_SSLKEY, $tempKey);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'SOAPAction: ""'
]);

$response = curl_exec($ch);
echo "Response:\n" . $response . "\n";
curl_close($ch);
@unlink($tempCert);
@unlink($tempKey);
