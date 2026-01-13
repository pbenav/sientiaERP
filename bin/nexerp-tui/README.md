# Sistema TUI Homogéneo - nexERP

## Descripción

Sistema de interfaz de usuario de texto (TUI) inspirado en IBM AS/400 y mainframes IBM serie Z con MVS. Proporciona una experiencia de usuario consistente y profesional con navegación estandarizada mediante teclas de función.

## Características Principales

### ✨ Interfaz Homogénea
- **Ventanas completas** dibujadas en cada interacción
- **Bordes y títulos** consistentes en todas las pantallas
- **Barra de funciones** siempre visible en la parte inferior

### ⌨️ Navegación Estandarizada

#### Teclas de Función (F1-F12)
| Tecla | Función | Descripción |
|-------|---------|-------------|
| **F1** | Ayuda | Muestra ayuda contextual |
| **F2** | Editar | Edita el registro seleccionado |
| **F5** | Crear | Crea un nuevo registro |
| **F6** | Modificar | Modifica el registro actual |
| **F8** | Eliminar | Elimina el registro (con confirmación) |
| **F10** | Guardar | Guarda los cambios en formularios |
| **F12** | Volver | Vuelve a la pantalla anterior / Cancela |

#### Navegación en Formularios
| Tecla | Función |
|-------|---------|
| **TAB** | Siguiente campo |
| **Shift+TAB** | Campo anterior |
| **Enter** | Siguiente campo |
| **Backspace** | Borrar carácter |

#### Navegación en Listas
| Tecla | Función |
|-------|---------|
| **↑** | Registro anterior |
| **↓** | Registro siguiente |
| **Page Up** | Página anterior |
| **Page Down** | Página siguiente |
| **Enter** | Ver detalles del registro |

#### Teclas Especiales
| Tecla | Función |
|-------|---------|
| **ESC** | Volver / Cancelar |
| **Ctrl+→** | Cerrar registro |

## Arquitectura

### Componentes

```
nexerp-tui/
├── src/
│   ├── Display/
│   │   ├── Screen.php           # Gestión de pantalla
│   │   ├── Window.php           # Ventanas completas
│   │   ├── ListController.php   # Controlador de listas
│   │   └── FormController.php   # Controlador de formularios
│   ├── Input/
│   │   ├── KeyHandler.php       # Manejo de teclas
│   │   └── FunctionKeyMapper.php # Mapeo de teclas de función
│   ├── Actions/
│   │   ├── TercerosActions.php  # Acciones de terceros
│   │   └── AlmacenActions.php   # Acciones de almacén
│   └── ErpClient.php            # Cliente API
└── SISTEMA_TUI.md               # Guía de desarrollo
```

### Flujo de Trabajo

1. **Usuario ejecuta** `nexerp-tui.php`
2. **Autenticación** contra el backend Laravel
3. **Menú principal** con opciones
4. **Navegación** mediante teclas de función
5. **Interacción** con listas y formularios
6. **Persistencia** de datos en el backend

## Uso

### Ejecutar el Cliente TUI

```bash
cd /home/pablo/Desarrollo/Laravel/nexERP
php bin/nexerp-tui.php
```

### Variables de Entorno

```bash
# URL del backend API
export ERP_API_URL="http://localhost:8000"
```

## Ejemplos de Uso

### Listar Terceros
1. Ejecutar cliente TUI
2. Seleccionar "Terceros" en el menú
3. Usar **↑↓** para navegar
4. Presionar **F5** para crear nuevo
5. Presionar **F2** o **F6** para editar
6. Presionar **Enter** para ver detalles
7. Presionar **F12** para volver

### Crear Nuevo Tercero
1. En lista de terceros, presionar **F5**
2. Rellenar campos usando **TAB** para navegar
3. Presionar **F10** para guardar
4. Presionar **F12** para cancelar

### Editar Tercero
1. Seleccionar tercero con **↑↓**
2. Presionar **F2** o **F6**
3. Modificar campos con **TAB**
4. Presionar **F10** para guardar

## Formatos de Datos

El sistema soporta varios formatos automáticos en listas:

- **currency**: `1.234,56 €`
- **percentage**: `21%`
- **date**: `31/12/2024`
- **datetime**: `31/12/2024 15:30`
- **boolean**: `Sí` / `No`

## Validación

Los formularios incluyen validación en tiempo real:

- ✅ Campos obligatorios marcados con `*`
- ✅ Validación de formato (email, números, etc.)
- ✅ Mensajes de error claros
- ✅ No permite guardar si hay errores

## Ventajas sobre el Sistema Anterior

| Aspecto | Sistema Anterior | Sistema Nuevo |
|---------|-----------------|---------------|
| **Navegación** | Teclas ad-hoc (n, e, p, s) | Teclas de función estandarizadas |
| **Formularios** | Laravel Prompts | FormController con TAB |
| **Listas** | Renderizado manual | ListController automático |
| **Paginación** | Manual | Automática |
| **Validación** | Básica | Completa con mensajes |
| **Consistencia** | Variable | Homogénea |
| **Código** | ~200 líneas/acción | ~50 líneas/acción |

## Migración

Para migrar acciones antiguas al nuevo sistema:

### Antes
```php
public function listar(): void {
    // 80+ líneas de código manual
    while ($running) {
        // Renderizado manual
        // Manejo manual de teclas
        // Paginación manual
    }
}
```

### Después
```php
public function listar(): void {
    $list = new ListController($this->keyHandler, $this->screen, 'TÍTULO');
    $list->setColumns([...]);
    $list->onFetch(fn($page, $perPage) => $this->client->getData($page, $perPage));
    $list->onCreate(fn() => $this->crear());
    $list->onEdit(fn($item) => $this->editar($item['id']));
    $list->run();
}
```

## Requisitos

- PHP 8.2+
- Terminal con soporte ANSI
- Extensión `readline` (opcional, mejora la experiencia)

## Compatibilidad

El sistema ha sido probado en:
- ✅ Linux (Ubuntu, Debian, Fedora)
- ✅ macOS
- ⚠️ Windows (requiere Windows Terminal o similar)

## Soporte

Para más información, consultar:
- `SISTEMA_TUI.md` - Guía de desarrollo detallada
- Código fuente en `src/Display/` y `src/Input/`

## Licencia

Parte del proyecto nexERP - Sistema de Gestión Empresarial

---

**¡Disfruta de la experiencia AS/400 en tu terminal moderna!** 🚀
