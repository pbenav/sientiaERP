# nexERP

**nexERP** es una solución completa de Punto de Venta (POS) diseñada para entornos de alto rendimiento donde la velocidad del teclado es la prioridad absoluta.

## Arquitectura

El sistema está compuesto por dos componentes principales:

### 1. Backend (Laravel + API REST)
- **Framework**: Laravel 12
- **Base de datos**: SQLite (configurable para MySQL/PostgreSQL)
- **API REST**: Endpoints seguros para gestión de productos, clientes y ventas
- **Estándares de seguridad**: Validación de datos, protección CSRF, preparado para autenticación

### 2. TUI Client (Terminal BASH)
- **Interfaz**: Terminal User Interface (TUI) optimizada para teclado
- **Comunicación**: Asíncrona con el backend vía curl
- **Requisitos**: BASH, curl, jq
- **Portabilidad**: Compatible con cualquier terminal BASH

## Características

### Backend API
- ✅ Gestión completa de productos (CRUD)
- ✅ Gestión de clientes (CRUD)
- ✅ Sistema de ventas con control de stock
- ✅ Cálculo automático de totales
- ✅ Validación de datos
- ✅ Base de datos con migraciones y seeders

### TUI Client
- ✅ Navegación por teclado optimizada
- ✅ Interfaz intuitiva con menús
- ✅ Gestión de productos
- ✅ Gestión de clientes
- ✅ Proceso de checkout/ventas
- ✅ Visualización de historial de ventas
- ✅ Configuración de API URL
- ✅ Colores y formato para mejor visualización

## Instalación

### Requisitos Previos

**Backend:**
- PHP >= 8.3
- Composer
- SQLite (o MySQL/PostgreSQL)

**TUI Client:**
- Bash
- curl
- jq

### Instalación del Backend

```bash
# Navegar al directorio del backend
cd backend

# Instalar dependencias (ya instaladas en el repositorio)
composer install

# Configurar el archivo .env (ya existe)
# Verificar configuración de base de datos en .env

# Ejecutar migraciones
php artisan migrate

# Sembrar datos de ejemplo (opcional)
php artisan db:seed

# Iniciar el servidor de desarrollo
php artisan serve
```

El servidor estará disponible en `http://localhost:8000`

### Instalación del TUI Client

```bash
# Instalar dependencias en sistemas Debian/Ubuntu
sudo apt-get install curl jq

# Dar permisos de ejecución al script (ya configurado)
chmod +x tui-client/nexerp-tui.sh

# Ejecutar el cliente TUI
./tui-client/nexerp-tui.sh
```

## Uso

### Backend API

#### Endpoints Disponibles

**Productos:**
- `GET /api/products` - Listar productos activos
- `POST /api/products` - Crear producto
- `GET /api/products/{id}` - Ver producto específico
- `PUT /api/products/{id}` - Actualizar producto
- `DELETE /api/products/{id}` - Eliminar producto

**Clientes:**
- `GET /api/customers` - Listar clientes activos
- `POST /api/customers` - Crear cliente
- `GET /api/customers/{id}` - Ver cliente específico
- `PUT /api/customers/{id}` - Actualizar cliente
- `DELETE /api/customers/{id}` - Eliminar cliente

**Ventas:**
- `GET /api/sales` - Listar ventas
- `POST /api/sales` - Crear venta
- `GET /api/sales/{id}` - Ver venta específica
- `DELETE /api/sales/{id}` - Eliminar venta

#### Ejemplos de uso con curl

```bash
# Listar productos
curl http://localhost:8000/api/products

# Crear un producto
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "code": "PROD006",
    "name": "Webcam HD",
    "description": "1080p webcam",
    "price": 59.99,
    "stock": 20
  }'

# Crear una venta
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 2
  }'
```

### TUI Client

1. **Iniciar el cliente:**
   ```bash
   ./tui-client/nexerp-tui.sh
   ```

2. **Navegación:**
   - Usa los números para seleccionar opciones
   - Presiona Enter para confirmar
   - Usa 0 para volver al menú anterior

3. **Funciones principales:**
   - **Productos (1)**: Gestionar inventario
   - **Clientes (2)**: Gestionar base de datos de clientes
   - **Ventas (3)**: Procesar ventas rápidamente
   - **Historial (4)**: Ver ventas realizadas
   - **Settings (5)**: Configurar URL del API

4. **Configuración:**
   - La configuración se guarda en `~/.nexerp/config`
   - Por defecto, el API URL es `http://localhost:8000/api`

## Estructura del Proyecto

```
nexERP/
├── backend/                 # Laravel Backend API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── ProductController.php
│   │   │   ├── CustomerController.php
│   │   │   └── SaleController.php
│   │   └── Models/
│   │       ├── Product.php
│   │       ├── Customer.php
│   │       └── Sale.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── ...
├── tui-client/             # BASH TUI Client
│   └── nexerp-tui.sh
└── README.md
```

## Modelos de Datos

### Product
- `id`: ID único
- `code`: Código del producto (único)
- `name`: Nombre del producto
- `description`: Descripción
- `price`: Precio (decimal)
- `stock`: Cantidad en stock
- `active`: Estado activo/inactivo

### Customer
- `id`: ID único
- `code`: Código del cliente (único)
- `name`: Nombre del cliente
- `email`: Email (opcional)
- `phone`: Teléfono (opcional)
- `address`: Dirección (opcional)
- `active`: Estado activo/inactivo

### Sale
- `id`: ID único
- `customer_id`: ID del cliente (opcional)
- `product_id`: ID del producto
- `quantity`: Cantidad vendida
- `unit_price`: Precio unitario
- `total`: Total de la venta
- `created_at`: Fecha de la venta

## Desarrollo

### Ejecutar tests
```bash
cd backend
php artisan test
```

### Linting
```bash
cd backend
./vendor/bin/pint
```

## Características de Seguridad

- ✅ Validación de datos en todas las peticiones
- ✅ Control de stock automático
- ✅ Protección contra inyección SQL (Eloquent ORM)
- ✅ Headers de seguridad HTTP
- ✅ Preparado para autenticación con Laravel Sanctum

## Roadmap

### Próximas características:
- [ ] Autenticación de usuarios (Laravel Sanctum)
- [ ] Reportes de ventas
- [ ] Gestión de categorías de productos
- [ ] Descuentos y promociones
- [ ] Multi-tienda
- [ ] Backup automático de base de datos
- [ ] Dashboard web con Filament
- [ ] Soporte para códigos de barras
- [ ] Impresión de recibos

## Contribuciones

Las contribuciones son bienvenidas. Por favor, abre un issue para discutir cambios mayores antes de crear un pull request.

## Licencia

Este proyecto está bajo la licencia incluida en el archivo LICENSE.

## Soporte

Para reportar bugs o solicitar nuevas características, por favor abre un issue en el repositorio.

## Créditos

- **Laravel**: Framework PHP para el backend
- **Filament** (planificado): Panel de administración
- **BASH**: Shell scripting para el TUI client

