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
$certPath = \App\Models\Setting::get('verifactu_cert_path');
$certPassword = \App\Models\Setting::get('verifactu_cert_password');
$endpoint = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP";

echo "Probando conexión a: $endpoint\n";
echo "Certificado: $certPath\n";

if (!$certPath) {
    die("ERROR: No hay certificado configurado.\n");
}

$fullPath = storage_path('app/private/' . $certPath);
if (!file_exists($fullPath)) {
    // Intentar ruta absoluta si es guardada así
    $fullPath = $certPath;
    if (!file_exists($fullPath)) {
        die("ERROR: Certificado no encontrado en $fullPath\n");
    }
}

try {
    $certs = $certService->loadP12($fullPath, $certPassword);
    $tempCert = $certService->createTempPem($certs['cert']);
    $tempKey = $certService->createTempPem($certs['pkey']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSLCERT, $tempCert);
    curl_setopt($ch, CURLOPT_SSLKEY, $tempKey);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);

    if (curl_errno($ch)) {
        echo 'CURL Error: ' . curl_error($ch) . "\n";
    } else {
        echo "HTTP Status: " . $info['http_code'] . "\n";
        echo "Response:\n" . substr($response, 0, 1000) . "...\n";
    }

    curl_close($ch);
    @unlink($tempCert);
    @unlink($tempKey);

} catch (\Exception $e) {
    echo "Excepción: " . $e->getMessage() . "\n";
}
