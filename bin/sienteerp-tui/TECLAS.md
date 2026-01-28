# Referencia Rápida de Teclas - sienteERP TUI

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║                      TECLAS DE FUNCIÓN - SISTEMA TUI                          ║
╚═══════════════════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────────────────────┐
│ TECLAS DE FUNCIÓN PRINCIPALES                                               │
└─────────────────────────────────────────────────────────────────────────────┘

  F1  = Ayuda             Muestra ayuda contextual de la pantalla actual
  F2  = Editar            Edita el registro seleccionado en listas
  F5  = Crear             Crea un nuevo registro
  F6  = Modificar         Modifica el registro actual
  F8  = Eliminar          Elimina el registro (solicita confirmación)
  F10 = Guardar           Guarda los cambios en formularios
  F12 = Volver/Cancelar   Vuelve a la pantalla anterior o cancela

┌─────────────────────────────────────────────────────────────────────────────┐
│ NAVEGACIÓN EN FORMULARIOS                                                   │
└─────────────────────────────────────────────────────────────────────────────┘

  TAB         = Siguiente campo
  Shift+TAB   = Campo anterior
  Enter       = Siguiente campo (alternativa a TAB)
  Backspace   = Borrar carácter
  F10         = Guardar formulario
  F12 / ESC   = Cancelar y volver

  Nota: Los campos obligatorios están marcados con *

┌─────────────────────────────────────────────────────────────────────────────┐
│ NAVEGACIÓN EN LISTAS                                                        │
└─────────────────────────────────────────────────────────────────────────────┘

  ↑           = Registro anterior
  ↓           = Registro siguiente
  Page Up     = Página anterior
  Page Down   = Página siguiente
  Enter       = Ver detalles del registro seleccionado
  F2 / F6     = Editar registro seleccionado
  F5          = Crear nuevo registro
  F8          = Eliminar registro seleccionado
  F12 / ESC   = Volver al menú anterior

┌─────────────────────────────────────────────────────────────────────────────┐
│ TECLAS ESPECIALES                                                           │
└─────────────────────────────────────────────────────────────────────────────┘

  ESC         = Volver / Cancelar (equivalente a F12)
  Ctrl+→      = Cerrar registro (función especial)
  Ctrl+C      = Salir de la aplicación (emergencia)

┌─────────────────────────────────────────────────────────────────────────────┐
│ INDICADORES VISUALES                                                        │
└─────────────────────────────────────────────────────────────────────────────┘

  ►           = Registro/campo seleccionado actualmente
  *           = Campo obligatorio
  ✓           = Operación exitosa
  ✗           = Error
  ⚠           = Advertencia
  ℹ           = Información

┌─────────────────────────────────────────────────────────────────────────────┐
│ COLORES                                                                     │
└─────────────────────────────────────────────────────────────────────────────┘

  Cyan        = Bordes y títulos
  Amarillo    = Selección actual, valores editables
  Verde       = Teclas de función, mensajes de éxito
  Rojo        = Errores, eliminación
  Blanco      = Texto normal, labels

┌─────────────────────────────────────────────────────────────────────────────┐
│ ATAJOS DE TECLADO POR CONTEXTO                                              │
└─────────────────────────────────────────────────────────────────────────────┘

  MENÚ PRINCIPAL:
    ↑↓        = Navegar opciones
    Enter     = Seleccionar opción
    ESC       = Salir

  LISTA DE REGISTROS:
    ↑↓        = Navegar registros
    F5        = Nuevo
    F2/F6     = Editar
    F8        = Eliminar
    Enter     = Ver detalles
    F12       = Volver

  FORMULARIO:
    TAB       = Siguiente campo
    Shift+TAB = Campo anterior
    F10       = Guardar
    F12       = Cancelar

  CONFIRMACIÓN:
    F10       = Confirmar
    F12       = Cancelar

┌─────────────────────────────────────────────────────────────────────────────┐
│ CONSEJOS                                                                    │
└─────────────────────────────────────────────────────────────────────────────┘

  • Siempre mira la barra de funciones en la parte inferior de la pantalla
  • Las teclas disponibles varían según el contexto
  • F1 siempre muestra ayuda contextual
  • F12 siempre vuelve atrás o cancela
  • Los mensajes de error se muestran en rojo
  • Los campos con errores no permiten guardar hasta corregirlos

┌─────────────────────────────────────────────────────────────────────────────┐
│ COMPATIBILIDAD CON AS/400                                                   │
└─────────────────────────────────────────────────────────────────────────────┘

  Este sistema replica la funcionalidad de los terminales IBM 5250 (AS/400)
  y 3270 (Mainframe). Si estás familiarizado con esos sistemas, te sentirás
  como en casa.

  Diferencias principales:
  • No hay teclas de atención (Attn)
  • No hay teclas de programa (PA1, PA2, PA3)
  • Enter funciona como TAB en formularios (además de su función normal)

╔═══════════════════════════════════════════════════════════════════════════════╗
║  Para más información, consulta README.md y SISTEMA_TUI.md                    ║
╚═══════════════════════════════════════════════════════════════════════════════╝
```
