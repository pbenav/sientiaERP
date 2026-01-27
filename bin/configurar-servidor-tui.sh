#!/bin/bash
# Script de configuración del servidor TUI de nexERP

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║     Configuración del Servidor TUI de nexERP              ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Archivo de configuración
TUI_ENV_FILE=".tui.env"

# Verificar si existe el archivo
if [ -f "$TUI_ENV_FILE" ]; then
    echo "📄 Configuración actual:"
    echo ""
    grep "^ERP_API_URL=" "$TUI_ENV_FILE" || echo "   (No configurada)"
    echo ""
else
    echo "⚠️  No se encontró el archivo .tui.env"
    echo ""
fi

echo "Seleccione el servidor a utilizar:"
echo ""
echo "  1) Servidor de pruebas (https://erp.contraste.online)"
echo "  2) Desarrollo local (http://localhost:8000)"
echo "  3) Introducir URL personalizada"
echo "  4) Ver configuración actual y salir"
echo ""
read -p "Opción [1-4]: " opcion

case $opcion in
    1)
        URL="https://erp.contraste.online"
        ;;
    2)
        URL="http://localhost:8000"
        ;;
    3)
        read -p "Introduce la URL del servidor (sin barra final): " URL
        ;;
    4)
        echo ""
        echo "Configuración actual:"
        if [ -f "$TUI_ENV_FILE" ]; then
            cat "$TUI_ENV_FILE"
        else
            echo "  No existe archivo de configuración"
        fi
        echo ""
        exit 0
        ;;
    *)
        echo "❌ Opción no válida"
        exit 1
        ;;
esac

# Crear o actualizar el archivo .tui.env
cat > "$TUI_ENV_FILE" << EOF
# Configuración del Cliente TUI de nexERP
# Este archivo permite configurar la URL del servidor API para el cliente TUI

# URL del servidor API (sin barra final)
# Ejemplos:
#   - Servidor de pruebas: https://erp.contraste.online
#   - Desarrollo local: http://localhost:8000
#   - Producción: https://tu-servidor.com

ERP_API_URL=$URL
EOF

echo ""
echo "✅ Configuración guardada correctamente"
echo ""
echo "📝 URL configurada: $URL"
echo ""
echo "Ahora puedes ejecutar el TUI con:"
echo "  php bin/nexerp-tui.php"
echo ""

# Verificar conectividad (opcional)
read -p "¿Deseas verificar la conectividad con el servidor? [s/N]: " verificar

if [[ "$verificar" =~ ^[sS]$ ]]; then
    echo ""
    echo "🔍 Verificando conectividad..."
    
    # Intentar hacer ping al servidor
    if command -v curl &> /dev/null; then
        if curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$URL" | grep -q "200\|302\|401"; then
            echo "✅ Servidor accesible"
        else
            echo "⚠️  No se pudo conectar al servidor (esto puede ser normal si requiere autenticación)"
        fi
    else
        echo "⚠️  curl no está instalado, no se puede verificar conectividad"
    fi
    echo ""
fi

echo "¡Listo!"
