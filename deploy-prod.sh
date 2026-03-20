#!/bin/bash

# Script de Despliegue y Optimización para SienteERP
# Este script automatiza la limpieza de cachés y la preparación de la app para producción.

# Colorines para la consola
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Iniciando despliegue de SienteERP...${NC}"

# Paso 0: Git (Opcional, se puede descomentar si el despliegue es vía Git)
# echo -e "${YELLOW}📥 Actualizando código desde Git...${NC}"
# git pull github main

# Paso 1: Dependencias
echo -e "${YELLOW}📦 Optimizando dependencias de PHP...${NC}"
composer install --optimize-autoloader --no-dev --no-interaction

# Paso 2: Migraciones
echo -e "${YELLOW}🗄️  Ejecutando migraciones...${NC}"
php artisan migrate --force

# Paso 3: Limpieza Profunda de Cachés
echo -e "${YELLOW}🧹 Limpiando cachés de Laravel y Filament...${NC}"
php artisan view:clear
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan filament:optimize-clear
php artisan icons:clear

# Paso 4: Optimización Final
echo -e "${YELLOW}🏎️  Regenerando optimización de Laravel (Route, Config, Events)...${NC}"
php artisan optimize

# Paso 5: Assets (Opcional, si se compila en el servidor)
# echo -e "${YELLOW}🏗️  Compilando assets (Vite)...${NC}"
# npm run build

# Paso 6: Reseteo de PHP (Solución para OPCache)
echo -e "${YELLOW}🌬️  Intentando reiniciar PHP-FPM para limpiar OPCache...${NC}"
# Detectar versión de PHP automáticamente (8.2, 8.3, 8.4...)
PHP_VER=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
SERVICE_NAME="php$PHP_VER-fpm"

if sudo systemctl is-active --quiet $SERVICE_NAME; then
    sudo systemctl restart $SERVICE_NAME
    echo -e "${GREEN}✅ $SERVICE_NAME reiniciado con éxito.${NC}"
else
    echo -e "${RED}⚠️  No se pudo reiniciar $SERVICE_NAME (comprueba el nombre del servicio).${NC}"
fi

echo -e "${GREEN}✨ ¡SienteERP actualizado y optimizado con éxito!${NC}"
