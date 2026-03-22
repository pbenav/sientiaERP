<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class CertificateService
{
    /**
     * Load a PKCS12 certificate and extract its components.
     * Supports OpenSSL 3 legacy encryption via CLI fallback.
     */
    public function loadP12(string $path, string $password): array
    {
        if (!file_exists($path)) {
            throw new Exception("Archivo de certificado no encontrado en: {$path}");
        }

        $p12content = file_get_contents($path);
        $certs = [];

        // 1. Intentar con la función nativa de PHP primero
        if (@openssl_pkcs12_read($p12content, $certs, $password)) {
            return $certs;
        }

        // 2. Si falla, es probable que sea por incompatibilidad de OpenSSL 3 con certificados legacy.
        // Intentar usar el CLI de openssl con el flag -legacy si está disponible.
        return $this->loadWithLegacyFallback($path, $password);
    }

    /**
     * Fallback al CLI de OpenSSL para manejar archivos PKCS12 legacy.
     */
    protected function loadWithLegacyFallback(string $path, string $password): array
    {
        $escapedPath = escapeshellarg($path);
        $escapedPass = escapeshellarg($password);
        
        // Comando para extraer toda la información en formato PEM usando el proveedor legacy
        $cmd = "openssl pkcs12 -in {$escapedPath} -passin pass:{$escapedPass} -nodes -legacy 2>&1";
        
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);
        
        $fullOutput = implode("\n", $output);

        if ($returnVar !== 0) {
            Log::error("Fallo en el fallback de certificado legacy: " . $fullOutput);
            throw new Exception("No se pudo procesar el archivo PKCS12 incluso con el fallback legacy. Comprueba la contraseña o la integridad del archivo.");
        }

        // Parsear componentes PEM de la salida
        return $this->parsePemOutput($fullOutput);
    }

    /**
     * Parsear la salida PEM de múltiples componentes del comando openssl pkcs12.
     */
    protected function parsePemOutput(string $output): array
    {
        $certs = [
            'cert' => '',
            'pkey' => '',
            'extracerts' => []
        ];

        // Extraer Clave Privada
        if (preg_match('/(-----BEGIN PRIVATE KEY-----.*?-----END PRIVATE KEY-----)/s', $output, $matches)) {
            $certs['pkey'] = $matches[1];
        } elseif (preg_match('/(-----BEGIN RSA PRIVATE KEY-----.*?-----END RSA PRIVATE KEY-----)/s', $output, $matches)) {
            $certs['pkey'] = $matches[1];
        }

        // Extraer Certificados
        if (preg_match_all('/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s', $output, $matches)) {
            if (count($matches[1]) > 0) {
                $certs['cert'] = $matches[1][0];
                if (count($matches[1]) > 1) {
                    $certs['extracerts'] = array_slice($matches[1], 1);
                }
            }
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new Exception("Error al extraer el certificado o la clave privada de la salida de OpenSSL.");
        }

        return $certs;
    }

    /**
     * Create a temporary PEM file for cURL/Guzzle.
     * Use a secure location and delete after use.
     */
    public function createTempPem(string $content): string
    {
        $tempDir = storage_path('app/temp_certs');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $filename = $tempDir . '/cert_' . bin2hex(random_bytes(8)) . '.pem';
        file_put_contents($filename, $content);
        chmod($filename, 0600);

        return $filename;
    }
}
