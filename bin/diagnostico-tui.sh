#!/bin/bash
# Script de diagnóstico para TUI en producción

echo "=== Diagnóstico TUI nexERP ==="
echo ""

echo "1. Directorio actual:"
pwd
echo ""

echo "2. Versión del código (últimos 3 commits):"
git log --oneline -3
echo ""

echo "3. Configuración de API URL en nexerp-tui.php:"
grep -A 1 "'api_url'" bin/nexerp-tui.php | head -2
echo ""

echo "4. Variables de entorno en .env:"
grep -E "^(APP_URL|ERP_API_URL|POS_API_URL)" .env 2>/dev/null || echo "No se encontraron variables de URL en .env"
echo ""

echo "5. Test de carga de .env:"
php -r "
\$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->safeLoad();
echo 'APP_URL: ' . (\$_ENV['APP_URL'] ?? 'NO DEFINIDA') . PHP_EOL;
echo 'ERP_API_URL: ' . (\$_ENV['ERP_API_URL'] ?? 'NO DEFINIDA') . PHP_EOL;
echo 'Fallback calculado: ' . (\$_ENV['ERP_API_URL'] ?? getenv('ERP_API_URL') ?: (\$_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8000')) . PHP_EOL;
"
echo ""

echo "=== Fin del diagnóstico ==="
