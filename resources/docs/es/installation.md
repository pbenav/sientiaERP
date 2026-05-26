# Guía de Instalación — sientiaERP

Esta guía describe el proceso completo para desplegar sientiaERP en un servidor de producción (VPS o dedicado con Linux).

## Requisitos del Sistema

### Servidor

| Componente | Versión mínima | Recomendada |
|---|---|---|
| PHP | 8.2 | 8.3 |
| MySQL/MariaDB | MySQL 8.0 / MariaDB 10.6 | MySQL 8.4 |
| Nginx / Apache | Cualquier versión reciente | Nginx 1.24+ |
| Node.js | 18 LTS | 20 LTS |
| Composer | 2.x | 2.7+ |
| RAM | 1 GB | 2 GB+ |
| Disco | 5 GB libres | 20 GB+ |

### Extensiones PHP Requeridas

```
bcmath  ctype  curl  fileinfo  gd  json  mbstring  openssl  pdo  pdo_mysql  tokenizer  xml  zip
```

Comprueba las extensiones activas con: `php -m`

---

## Paso 1: Clonar el Repositorio

```bash
cd /var/www
git clone git@github.com:pbenav/sientiaerp.git sientiaERP
cd sientiaERP
```

## Paso 2: Instalar Dependencias

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

## Paso 3: Configuración del Entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita el archivo `.env` con los datos de tu entorno:

```env
APP_NAME="sientiaERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sientia_erp
DB_USERNAME=usuario_db
DB_PASSWORD=contraseña_segura

MAIL_MAILER=smtp
MAIL_HOST=smtp.tuproveedor.com
MAIL_PORT=587
MAIL_USERNAME=correo@tudominio.com
MAIL_PASSWORD=contraseña_correo
MAIL_FROM_ADDRESS=correo@tudominio.com
MAIL_FROM_NAME="sientiaERP"
```

## Paso 4: Base de Datos

```bash
php artisan migrate --seed
```

> Esto crea todas las tablas y genera los datos iniciales (tipos de IVA, series de facturación, usuario administrador por defecto).

## Paso 5: Storage y Permisos

```bash
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Paso 6: Optimización para Producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## Configuración de Nginx

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;
    root /var/www/sientiaERP/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activa SSL con Let's Encrypt:

```bash
certbot --nginx -d tudominio.com -d www.tudominio.com
```

## Configuración del Scheduler (Cron)

Añade al crontab del usuario `www-data`:

```bash
* * * * * cd /var/www/sientiaERP && php artisan schedule:run >> /dev/null 2>&1
```

---

## Actualización

Para actualizar a una versión nueva:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan optimize
```

---

## Solución de Problemas

| Problema | Solución |
|---|---|
| Error 500 en producción | Revisa `storage/logs/laravel.log` |
| Las imágenes no cargan | Ejecuta `php artisan storage:link` |
| El cron no funciona | Verifica con `php artisan schedule:list` |
| Error de permisos | `chown -R www-data:www-data storage` |
