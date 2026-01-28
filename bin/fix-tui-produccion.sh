#!/bin/bash
# Script de solución rápida para TUI en producción

echo "=== Solución rápida TUI sientiaERP ==="
echo ""

# 1. Actualizar código
echo "1. Actualizando código desde repositorio..."
git pull origin feature/automatizacion-albaranes-compra
echo ""

# 2. Verificar si APP_URL existe en .env
echo "2. Verificando configuración de .env..."
if grep -q "^APP_URL=" .env; then
    echo "   ✓ APP_URL ya está configurada:"
    grep "^APP_URL=" .env
else
    echo "   ✗ APP_URL no encontrada, añadiendo..."
    echo "APP_URL=https://erp.contraste.online" >> .env
    echo "   ✓ APP_URL añadida a .env"
fi
echo ""

# 3. Mostrar configuración final
echo "3. Configuración final de URLs:"
grep -E "^(APP_URL|ERP_API_URL|POS_API_URL)" .env
echo ""

# 4. Verificar que el código está actualizado
echo "4. Verificando código actualizado:"
grep "'api_url'" bin/sientiaerp-tui.php | head -1
echo ""

echo "=== Solución completada ==="
echo ""
echo "Ahora puedes ejecutar: php bin/sientiaerp-tui.php"
