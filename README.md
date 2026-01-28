# sienteERP - Sistema Híbrido POS + ERP

Sistema completo de gestión empresarial con doble interfaz: web (Filament) y terminal (TUI).

## 🏗️ Arquitectura

### Módulos Implementados

1. **Sistema POS** - Punto de Venta
   - Backend Laravel 11 + Filament
   - API REST con Sanctum
   - Cliente TUI de alto rendimiento

2. **Sistema ERP** - Gestión Empresarial
   - Gestión de terceros (clientes/proveedores)
   - Documentos de negocio (presupuestos → facturas)
   - Cliente TUI con tmux multi-panel

## 📋 Requisitos

- PHP 8.2+
- Composer
- MySQL 8.0+ / MariaDB 10.6+
- Redis 6.0+
- tmux (para cliente ERP TUI)

## 🚀 Instalación

```bash
# 1. Instalar dependencias
composer install

# 2. Configurar .env
cp .env.example .env
# Editar DB_CONNECTION, DB_DATABASE, etc.

# 3. Generar clave
php artisan key:generate

# 4. Ejecutar migraciones
php artisan migrate

# 5. Crear usuario admin
php artisan make:filament-user

# 6. Iniciar servidor
php artisan serve
```

## 📖 Uso del Sistema

### Panel Web (Filament)

```bash
php artisan serve
# Acceder a: http://localhost:8000/admin
```

**Módulos disponibles:**
- **Productos** - Gestión de catálogo
- **Tickets** - Historial de ventas POS
- **Terceros** - Clientes y proveedores
- **Presupuestos** - Gestión de presupuestos

### Cliente POS (Terminal)

```bash
php bin/pos-tui.php
```

**Características:**
- Interfaz retro estilo Leroy Merlin
- Escaneo de códigos de barras
- Cálculos automáticos
- Checkout con cambio
- Sub-100ms de respuesta

**Atajos:**
- `F1` - Buscar producto
- `F5` - Cobrar
- `F6` - Totales del turno
- `F7` - Salir
- `ESC` - Cancelar línea

### Cliente ERP (Terminal con tmux)

```bash
# Instalar tmux si no lo tienes
sudo apt install tmux

# Ejecutar cliente
php bin/erp-tui.php
```

**Interfaz multi-panel:**
```
+------------------+------------------+
|   Menú/Lista     |   Detalles       |
+------------------+------------------+
|     Estado y Atajos de Teclado      |
+-------------------------------------+
```

**Navegación:**
- `↑/↓` - Navegar menú
- `Enter` - Seleccionar
- `Q` - Salir

## 🗄️ Base de Datos

### Tablas POS
- `products` - Catálogo de productos
- `customers` - Clientes (legacy, migrar a terceros)
- `tickets` - Tickets de venta
- `ticket_items` - Líneas de tickets

### Tablas ERP
- `tipo_tercero` - Tipos (Cliente, Proveedor, etc.)
- `terceros` - Clientes/Proveedores unificados
- `documentos` - Todos los documentos de negocio
- `documento_lineas` - Líneas de documentos
- `numeracion_documentos` - Control de numeración

## 🔌 API REST

### Autenticación
```http
POST /api/pos/login
Content-Type: application/json

{
  "email": "usuario@ejemplo.com",
  "password": "contraseña"
}
```

### Endpoints POS (requieren token)
```http
GET  /api/pos/product/{code}
POST /api/pos/ticket/create
POST /api/pos/ticket/add-item
POST /api/pos/ticket/checkout
GET  /api/pos/totals
```

## 📊 Estructura del Proyecto

```
sienteerp/
├── app/
│   ├── Filament/Resources/
│   │   ├── ProductResource.php
│   │   ├── TicketResource.php
│   │   ├── TerceroResource.php
│   │   └── PresupuestoResource.php
│   ├── Http/Controllers/Api/
│   │   └── PosController.php
│   └── Models/
│       ├── Product.php
│       ├── Ticket.php
│       ├── TipoTercero.php
│       ├── Tercero.php
│       ├── Documento.php
│       └── DocumentoLinea.php
├── bin/
│   ├── pos-tui.php              # Cliente POS
│   ├── erp-tui.php              # Cliente ERP (tmux)
│   ├── pos-tui/src/             # Componentes POS TUI
│   └── erp-tui/src/             # Componentes ERP TUI
└── database/migrations/
    ├── 2026_01_10_000001_create_products_table.php
    ├── 2026_01_10_000010_create_tipo_tercero_table.php
    ├── 2026_01_10_000011_create_terceros_table.php
    └── 2026_01_10_000012_create_documentos_table.php
```

## ✅ Características Implementadas

### Sistema POS
- ✅ Gestión de productos con stock
- ✅ Cliente TUI de alto rendimiento
- ✅ API REST completa
- ✅ Autenticación con Sanctum
- ✅ Gestión de sesiones con Redis
- ✅ Cálculos automáticos de IVA

### Sistema ERP
- ✅ Gestión de terceros (clientes/proveedores)
- ✅ Tipos de tercero configurables
- ✅ Presupuestos con líneas
- ✅ Numeración automática de documentos
- ✅ Cálculos automáticos (IVA, IRPF, totales)
- ✅ Conversión entre documentos
- ✅ Cliente TUI con tmux

## 🚧 Pendiente de Implementar

- [ ] Recursos Filament: Pedidos, Albaranes, Facturas, Recibos
- [ ] Listados completos en TUI ERP
- [ ] Formularios de creación en TUI ERP
- [ ] Generación de PDF para documentos
- [ ] Dashboard con estadísticas
- [ ] Informes de ventas

## 📝 Documentación Adicional

- [DEPLOYMENT.md](DEPLOYMENT.md) - Guía de despliegue en producción
- [ERP-TUI-README.md](ERP-TUI-README.md) - Documentación del cliente TUI ERP

## 🐛 Troubleshooting

**Error de base de datos:**
```bash
# Verificar configuración en .env
php artisan config:clear
php artisan migrate:fresh
```

**Cliente TUI no conecta:**
```bash
# Verificar que Laravel esté corriendo
php artisan serve

# Verificar variable de entorno
export POS_API_URL=http://localhost:8000
```

**tmux no funciona:**
```bash
# Instalar tmux
sudo apt install tmux

# Matar sesión colgada
tmux kill-session -t sienteerp-tui
```

## 📄 Licencia

MIT License

