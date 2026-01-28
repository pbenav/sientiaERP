#!/usr/bin/env php
<?php
/**
 * Script de verificación de configuración TUI
 * Muestra qué URL se está usando sin necesidad de autenticarse
 */

require __DIR__ . '/../vendor/autoload.php';

echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║     Verificación de Configuración TUI de sientiaERP       ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Cargar variables de entorno igual que el TUI
$tuiEnvPath = __DIR__ . '/../.tui.env';
if (file_exists($tuiEnvPath)) {
    $dotenvTui = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.tui.env');
    $dotenvTui->safeLoad();
    echo "✅ Archivo .tui.env encontrado y cargado\n\n";
} else {
    echo "⚠️  Archivo .tui.env NO encontrado\n\n";
}

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Mostrar configuración detectada
echo "📋 Variables de entorno detectadas:\n";
echo "   ├─ ERP_API_URL: " . ($_ENV['ERP_API_URL'] ?? getenv('ERP_API_URL') ?: '(no definida)') . "\n";
echo "   ├─ POS_API_URL: " . ($_ENV['POS_API_URL'] ?? getenv('POS_API_URL') ?: '(no definida)') . "\n";
echo "   └─ APP_URL: " . ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '(no definida)') . "\n";
echo "\n";

// Calcular URL final que usará el TUI
$finalUrl = $_ENV['ERP_API_URL'] ?? getenv('ERP_API_URL') ?: ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8000');

echo "🎯 URL que usará el TUI:\n";
echo "   \033[1;32m" . $finalUrl . "\033[0m\n\n";

// Verificar conectividad
echo "🔍 Verificando conectividad...\n";

$ch = curl_init($finalUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 500) {
    echo "   ✅ Servidor accesible (HTTP $httpCode)\n";
} else if ($error) {
    echo "   ❌ Error de conexión: $error\n";
} else {
    echo "   ⚠️  Respuesta inesperada (HTTP $httpCode)\n";
}

echo "\n";
echo "Para cambiar el servidor, ejecuta:\n";
echo "   \033[1;36mbash bin/configurar-servidor-tui.sh\033[0m\n\n";
