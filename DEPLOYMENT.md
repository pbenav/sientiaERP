# Guía de Despliegue - Sistema POS

## Configuración de Producción

### 1. Servidor Backend

#### Requisitos del Servidor
- Ubuntu 20.04+ / Debian 11+ / CentOS 8+
- PHP 8.2 FPM
- Nginx o Apache
- MySQL 8.0+ o MariaDB 10.6+
- Redis 6.0+
- Supervisor (para queues)

#### Instalación de Dependencias (Ubuntu/Debian)

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.2 y extensiones
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip

# Instalar MySQL
sudo apt install -y mysql-server

# Instalar Redis
sudo apt install -y redis-server

# Instalar Nginx
sudo apt install -y nginx

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### Configuración de Nginx

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/sienteerp/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Despliegue de la Aplicación

```bash
# Clonar/copiar proyecto
cd /var/www
sudo mkdir sienteerp
sudo chown $USER:$USER sienteerp
cd sienteerp

# Instalar dependencias
composer install --optimize-autoloader --no-dev

# Configurar permisos
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Configurar .env
cp .env.example .env
nano .env

# Configurar en .env:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sienteerp_pos
DB_USERNAME=sienteerp_user
DB_PASSWORD=contraseña_segura

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Generar clave
php artisan key:generate

# Ejecutar migraciones
php artisan migrate --force

# Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# Crear usuario admin
php artisan make:filament-user
```

#### Configurar Supervisor para Queues

```bash
sudo nano /etc/supervisor/conf.d/sienteerp-worker.conf
```

```ini
[program:sienteerp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sienteerp/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sienteerp/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sienteerp-worker:*
```

### 2. Terminales POS

#### Configuración de Terminal

```bash
# En cada terminal de caja
cd /opt
sudo git clone https://tu-repo.git sienteerp-pos
cd sienteerp-pos

# Instalar solo dependencias necesarias
composer install --no-dev --optimize-autoloader

# Configurar URL del servidor
echo 'export POS_API_URL=https://tu-dominio.com' >> ~/.bashrc
source ~/.bashrc

# Hacer ejecutable
chmod +x bin/pos-tui.php

# Crear alias para fácil acceso
echo 'alias pos="php /opt/sienteerp-pos/bin/pos-tui.php"' >> ~/.bashrc
source ~/.bashrc
```

#### Auto-inicio del Cliente TUI (opcional)

Para terminales dedicadas, configurar auto-inicio:

```bash
# Crear script de inicio
sudo nano /usr/local/bin/pos-autostart
```

```bash
#!/bin/bash
sleep 5  # Esperar a que el sistema esté listo
cd /opt/sienteerp-pos
php bin/pos-tui.php
```

```bash
sudo chmod +x /usr/local/bin/pos-autostart

# Añadir a autostart del usuario
mkdir -p ~/.config/autostart
nano ~/.config/autostart/pos.desktop
```

```ini
[Desktop Entry]
Type=Application
Name=POS Terminal
Exec=/usr/local/bin/pos-autostart
Terminal=true
```

### 3. Seguridad

#### Firewall

```bash
# Permitir solo puertos necesarios
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

#### SSL/TLS (Certbot)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com
```

#### Rate Limiting

Ya configurado en la API. Para ajustar:

```php
// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    // 60 requests por minuto
});
```

### 4. Backup

#### Script de Backup Automático

```bash
sudo nano /usr/local/bin/sienteerp-backup
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/sienteerp"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup de base de datos
mysqldump -u sienteerp_user -p'contraseña' sienteerp_pos | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup de archivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/sienteerp/storage

# Limpiar backups antiguos (más de 30 días)
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete
```

```bash
sudo chmod +x /usr/local/bin/sienteerp-backup

# Programar backup diario
sudo crontab -e
# Añadir:
0 2 * * * /usr/local/bin/sienteerp-backup
```

### 5. Monitoreo

#### Logs

```bash
# Ver logs de Laravel
tail -f /var/www/sienteerp/storage/logs/laravel.log

# Ver logs de Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Ver logs de workers
tail -f /var/www/sienteerp/storage/logs/worker.log
```

#### Health Check

Crear endpoint de health check:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::connection()->ping() ? 'connected' : 'disconnected',
    ]);
});
```

### 6. Actualización

```bash
cd /var/www/sienteerp

# Modo mantenimiento
php artisan down

# Actualizar código
git pull origin main

# Actualizar dependencias
composer install --no-dev --optimize-autoloader

# Ejecutar migraciones
php artisan migrate --force

# Limpiar y reconstruir cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Salir de mantenimiento
php artisan up
```

## Troubleshooting en Producción

### Error 500

```bash
# Verificar logs
tail -n 100 storage/logs/laravel.log

# Verificar permisos
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Base de datos no conecta

```bash
# Verificar servicio MySQL
sudo systemctl status mysql

# Probar conexión
mysql -u sienteerp_user -p -h 127.0.0.1 sienteerp_pos
```

### Redis no conecta

```bash
# Verificar servicio
sudo systemctl status redis-server

# Probar conexión
redis-cli ping
```

### Performance lento

```bash
# Verificar uso de recursos
htop

# Optimizar base de datos
php artisan db:optimize

# Limpiar cache
php artisan cache:clear
php artisan view:clear
```
