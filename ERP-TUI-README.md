# Cliente TUI para ERP - nexERP

Cliente de terminal con interfaz dividida usando tmux para gestión de terceros y documentos de negocio.

## Requisitos

- PHP 8.2+
- tmux instalado: `sudo apt install tmux`
- Composer dependencies

## Instalación

```bash
# Instalar tmux si no lo tienes
sudo apt install tmux

# Actualizar autoload
composer dump-autoload
```

## Uso

### Iniciar Cliente ERP TUI

```bash
# Asegúrate de que el servidor Laravel esté corriendo
php artisan serve

# En otra terminal, ejecuta el cliente TUI
php bin/erp-tui.php
```

### Interfaz con tmux

El cliente crea automáticamente una sesión tmux con 3 paneles:

```
+------------------+------------------+
|                  |                  |
|   Menú/Lista     |   Detalles       |
|                  |                  |
+------------------+------------------+
|          Acciones/Estado            |
+-------------------------------------+
```

**Panel Izquierdo (Menú)**:
- Navegación con flechas ↑/↓
- Menú principal con opciones:
  1. Terceros
  2. Presupuestos
  3. Pedidos
  4. Albaranes
  5. Facturas
  6. Recibos
  Q. Salir

**Panel Derecho (Detalles)**:
- Muestra información detallada del elemento seleccionado
- Formularios para crear/editar

**Panel Inferior (Estado)**:
- Estado de conexión
- Atajos de teclado
- Mensajes del sistema

### Navegación

- **↑/↓**: Navegar por el menú
- **Enter**: Seleccionar opción
- **F1**: Ayuda
- **F2**: Buscar
- **F10** o **Q**: Salir

### Salir del Cliente

- Presiona `Q` en el menú principal
- O presiona `Ctrl+B` luego `D` para detach de tmux
- Para matar la sesión: `tmux kill-session -t nexerp-tui`

## Estructura

```
bin/
├── erp-tui.php              # Ejecutable principal
├── erp-tui-menu.php         # Panel de menú
├── erp-tui-detail.php       # Panel de detalles
├── erp-tui-status.php       # Panel de estado
└── erp-tui/src/
    ├── TmuxManager.php      # Gestor de sesiones tmux
    ├── Display/
    │   └── Screen.php       # Renderizado (compartido con POS)
    └── Input/
        └── KeyHandler.php   # Input (compartido con POS)
```

## Características

✅ **Interfaz Multi-Panel**: 3 zonas independientes con tmux
✅ **Navegación por Teclado**: 100% keyboard-driven
✅ **Menú Interactivo**: Navegación con flechas
✅ **Autenticación**: Login con credenciales de usuario
✅ **Gestión de Terceros**: Listar, crear, buscar clientes/proveedores
✅ **Gestión de Documentos**: Presupuestos, pedidos, albaranes, facturas, recibos

## Próximas Implementaciones

- [ ] Listado de terceros con paginación
- [ ] Formulario de creación de terceros
- [ ] Listado de presupuestos
- [ ] Formulario de creación de presupuestos
- [ ] Búsqueda rápida (F2)
- [ ] Conversión entre documentos
- [ ] Impresión de documentos

## Troubleshooting

### tmux no está instalado

```bash
sudo apt install tmux
```

### Error de permisos

```bash
chmod +x bin/erp-tui*.php
```

### Sesión tmux colgada

```bash
tmux kill-session -t nexerp-tui
```

### Paneles no se muestran correctamente

Redimensiona la terminal a un tamaño mínimo de 120x30 caracteres.
