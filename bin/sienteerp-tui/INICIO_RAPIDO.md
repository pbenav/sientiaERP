# Inicio Rápido - Cliente TUI sienteERP

## 🚀 Ejecutar el Cliente

### 1. Asegúrate de que el servidor Laravel está corriendo

```bash
# En una terminal
cd /home/pablo/Desarrollo/Laravel/sienteERP
php artisan serve
```

### 2. Ejecuta el cliente TUI

```bash
# En otra terminal
cd /home/pablo/Desarrollo/Laravel/sienteERP
php bin/sienteerp-tui.php
```

## 📖 Primera Vez - Tutorial Interactivo

### Paso 1: Login
El sistema se autenticará automáticamente en modo desarrollo con:
- Email: `admin@sientia.com`
- Password: `12345678`

### Paso 2: Menú Principal
Verás el menú principal con opciones:
```
╔═══════════════════════════════════════════════════════════════╗
║               sienteERP - MENÚ PRINCIPAL                         ║
╚═══════════════════════════════════════════════════════════════╝

► [1] Ventas           
  [2] Terceros
  [3] Almacén
  [Q] Salir de la aplicación
```

**Navega con:**
- `↑↓` - Mover entre opciones
- `Enter` - Seleccionar
- `Q` o `ESC` - Salir

### Paso 3: Probar el Nuevo Sistema - Terceros

1. **Selecciona "Terceros"** (opción 2)
2. **Selecciona "Clientes"** o "Todos los Terceros"
3. Verás la lista con el **nuevo sistema**:

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║                           Todos los Terceros                                  ║
╚═══════════════════════════════════════════════════════════════════════════════╝

Página 1 de 1  |  Total: 5 terceros

  Código      Nombre                             NIF/CIF        Tipos           
  ────────────────────────────────────────────────────────────────────────────────
► TER-001     Sientia Soft                       B12345678      Cliente         
  TER-002     Supermercados Paco                  A98765432      Cliente         
  TER-003     Distribuciones García              B11111111      Proveedor       

  ────────────────────────────────────────────────────────────────────────────────

  F5=Crear  F2/F6=Editar  F8=Eliminar  ↑↓=Navegar  Enter=Ver  F12=Volver
```

### Paso 4: Crear un Nuevo Tercero

1. **Presiona F5** (Crear)
2. Verás el formulario:

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║                           NUEVO TERCERO                                       ║
╠═══════════════════════════════════════════════════════════════════════════════╣

  ► * Nombre Comercial/Razón Social: _

    * NIF/CIF:                        

      Email:                          

      Teléfono:                       

╠═══════════════════════════════════════════════════════════════════════════════╣
║ F1=Ayuda  F10=Guardar  F12=Cancelar  TAB=Siguiente  Shift+TAB=Anterior       ║
╚═══════════════════════════════════════════════════════════════════════════════╝
```

3. **Escribe el nombre** y presiona `TAB` para ir al siguiente campo
4. **Rellena los campos** navegando con `TAB` y `Shift+TAB`
5. **Presiona F10** para guardar o **F12** para cancelar

### Paso 5: Editar un Tercero

1. **Selecciona un tercero** con `↑↓`
2. **Presiona F2** o **F6**
3. **Modifica los campos** con `TAB`
4. **Presiona F10** para guardar

### Paso 6: Ver Detalles

1. **Selecciona un tercero** con `↑↓`
2. **Presiona Enter**
3. Verás los detalles completos
4. **Presiona cualquier tecla** para volver

### Paso 7: Probar Almacén

1. **Vuelve al menú principal** con `F12` o `ESC`
2. **Selecciona "Almacén"** (opción 3)
3. Verás la lista de productos con el mismo sistema
4. **Prueba las mismas teclas**: F5, F2, F6, ↑↓, Enter

## 🎯 Resumen de Teclas

### En Listas
| Tecla | Acción |
|-------|--------|
| `↑↓` | Navegar registros |
| `F5` | Crear nuevo |
| `F2` o `F6` | Editar seleccionado |
| `F8` | Eliminar seleccionado |
| `Enter` | Ver detalles |
| `Page Up/Down` | Cambiar página |
| `F12` o `ESC` | Volver |

### En Formularios
| Tecla | Acción |
|-------|--------|
| `TAB` | Siguiente campo |
| `Shift+TAB` | Campo anterior |
| `Enter` | Siguiente campo |
| `Backspace` | Borrar carácter |
| `F10` | Guardar |
| `F12` o `ESC` | Cancelar |
| `F1` | Ayuda |

## ⚠️ Notas Importantes

1. **Modo Desarrollo**: El login es automático. Para producción, cambia `$devMode = false` en `bin/sienteerp-tui.php`

2. **Servidor Laravel**: Debe estar corriendo en `http://localhost:8000` (o la URL configurada)

3. **Terminal**: Funciona mejor en terminales con soporte ANSI (Linux, macOS, Windows Terminal)

4. **Salir**: Presiona `Q` en el menú principal o `Ctrl+C` en cualquier momento

## 🐛 Solución de Problemas

### Error: "tmux no está instalado"
```bash
sudo apt install tmux  # Ubuntu/Debian
```

### Error: "No se puede conectar al servidor"
Verifica que Laravel esté corriendo:
```bash
php artisan serve
```

### Error: "Token inválido"
El token expira. Simplemente reinicia el cliente TUI.

### La pantalla no se ve bien
Asegúrate de usar un terminal con soporte ANSI y colores.

## 📚 Más Información

- `README.md` - Documentación completa
- `SISTEMA_TUI.md` - Guía de desarrollo
- `TECLAS.md` - Referencia completa de teclas
- `IMPLEMENTACION.md` - Detalles técnicos

## ✨ ¡Disfruta del Sistema!

Ahora tienes un cliente TUI profesional tipo AS/400. Explora todas las funcionalidades y familiarízate con las teclas de función.

**¡Bienvenido al futuro del pasado!** 🚀
