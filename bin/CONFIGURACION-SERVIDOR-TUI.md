# Configuración del Servidor TUI

## Problema Resuelto

El TUI intentaba conectarse a `localhost` cuando debería conectarse al servidor de pruebas. Ahora la configuración del servidor está parametrizada y es fácil de cambiar.

## Solución Implementada

Se ha creado un sistema de configuración específico para el TUI que permite cambiar fácilmente entre diferentes servidores sin modificar el código.

### Archivos Creados

1. **`.tui.env`** - Archivo de configuración del servidor TUI
2. **`bin/configurar-servidor-tui.sh`** - Script interactivo para configurar el servidor

## Uso Rápido

### Opción 1: Script Interactivo (Recomendado)

```bash
bash bin/configurar-servidor-tui.sh
```

El script te permite seleccionar entre:
- Servidor de pruebas (https://erp.contraste.online)
- Desarrollo local (http://localhost:8000)
- URL personalizada

### Opción 2: Edición Manual

Edita el archivo `.tui.env` en la raíz del proyecto:

```bash
nano .tui.env
```

Cambia la línea `ERP_API_URL` a la URL deseada:

```env
ERP_API_URL=https://erp.contraste.online
```

## Prioridad de Configuración

El sistema busca la configuración en el siguiente orden:

1. **`.tui.env`** (específico del TUI) ← **PRIORIDAD MÁXIMA**
2. `.env` → Variable `ERP_API_URL`
3. `.env` → Variable `APP_URL`
4. Fallback: `http://localhost:8000`

## Aplicaciones Afectadas

Esta configuración funciona para ambos clientes TUI:

- **`bin/sientiaerp-tui.php`** - Cliente ERP completo
- **`bin/pos-tui.php`** - Cliente POS (Punto de Venta)

Ambos usan el mismo archivo `.tui.env` para su configuración.

## Verificación

Para verificar que la configuración está correcta:

```bash
# Ver la configuración actual
cat .tui.env

# Ejecutar el TUI y verificar la URL en el mensaje de autenticación
php bin/sientiaerp-tui.php
```

Durante la autenticación, verás un mensaje como:
```
Autenticando en https://erp.contraste.online...
```

## Ejemplos de Configuración

### Servidor de Pruebas
```env
ERP_API_URL=https://erp.contraste.online
```

### Desarrollo Local
```env
ERP_API_URL=http://localhost:8000
```

### Servidor de Producción
```env
ERP_API_URL=https://produccion.miempresa.com
```

## Notas Importantes

- El archivo `.tui.env` **NO** debe incluirse en el control de versiones (ya está en `.gitignore`)
- Cada desarrollador/servidor puede tener su propia configuración
- No es necesario modificar el código fuente para cambiar de servidor
- Los cambios en `.tui.env` tienen efecto inmediato (no requiere reiniciar servicios)
