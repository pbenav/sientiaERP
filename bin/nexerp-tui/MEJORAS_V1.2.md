# Mejoras en Edición de Documentos - v1.2

## 🎯 Nueva Funcionalidad: Editor de Líneas

### **Problema Anterior:**
La edición de documentos no permitía modificar las líneas (artículos). Solo se podía cambiar la fecha.

### **Solución Implementada:**
Nuevo componente `DocumentLinesEditor` que permite:
- ✅ **Ver todas las líneas** en formato tabla
- ✅ **Navegar con cursores** ↑↓ entre líneas
- ✅ **Añadir líneas** con F5
- ✅ **Editar líneas** con F2/F6
- ✅ **Eliminar líneas** con F8 (con confirmación)
- ✅ **Guardar cambios** con F10

---

## 📋 Flujo de Edición de Documentos

### **Paso 1: Editar Datos Básicos**
```
╔═══════════════════════════════════════════════════════════════╗
║           EDITAR DOCUMENTO - DATOS BÁSICOS                    ║
╠═══════════════════════════════════════════════════════════════╣

    Número:              PRE-2026-0001
  ► Fecha (YYYY-MM-DD):  2026-01-11
    Cliente:             Sientia Soft
    Estado:              Borrador
    Total Actual:        3.811,50 €

╠═══════════════════════════════════════════════════════════════╣
║ F10=Continuar  F12=Cancelar                                   ║
╚═══════════════════════════════════════════════════════════════╝
```

### **Paso 2: Editar Líneas**
```
╔═══════════════════════════════════════════════════════════════╗
║              EDITAR DOCUMENTO - LÍNEAS                        ║
╚═══════════════════════════════════════════════════════════════╝

  Producto                           Cant.      Precio   Desc%        Total
  ─────────────────────────────────────────────────────────────────────────
► Escritorio Elevable                 2,00     450,00 €     0%      900,00 €
  Silla Ergonómica                    4,00     320,00 €    10%    1.152,00 €
  Monitor 27"                         2,00     299,00 €     5%      568,10 €
  ─────────────────────────────────────────────────────────────────────────
                                                        TOTAL:    2.620,10 €

╔═══════════════════════════════════════════════════════════════╗
║ F5=Añadir  F2/F6=Editar  F8=Eliminar  ↑↓=Navegar             ║
║ F10=Guardar  F12=Cancelar                                     ║
╚═══════════════════════════════════════════════════════════════╝
```

---

## 🎮 Teclas de Función

### En Editor de Líneas

| Tecla | Función |
|-------|---------|
| **↑↓** | Navegar entre líneas |
| **F5** | Añadir nueva línea (abre modal con autocompletado) |
| **F2** o **F6** | Editar línea seleccionada (cantidad y descuento) |
| **F8** | Eliminar línea seleccionada (con confirmación) |
| **F10** | Guardar cambios y continuar |
| **F12** | Cancelar edición |

---

## ✨ Características del Editor

### 1. **Visualización Clara**
- Tabla con columnas alineadas
- Producto, Cantidad, Precio, Descuento y Total
- Indicador visual de línea seleccionada (►)
- Total general actualizado en tiempo real

### 2. **Navegación Intuitiva**
- Cursores ↑↓ para seleccionar líneas
- Indicador visual de la línea actual
- Scroll automático (futuro)

### 3. **Edición Completa**
- **Añadir**: Modal con autocompletado de productos
- **Editar**: Modificar cantidad y descuento
- **Eliminar**: Con confirmación de seguridad

### 4. **Cálculos Automáticos**
- Total por línea (cantidad × precio × (1 - descuento%))
- Total general del documento
- Actualización en tiempo real

---

## 📊 Ejemplo de Uso

### **Editar un Presupuesto:**

1. **Listar presupuestos** → F2 en el presupuesto deseado
2. **Editar datos básicos** → Cambiar fecha si es necesario → F10
3. **Editar líneas:**
   - ↑↓ para seleccionar línea
   - F2 para editar cantidad/descuento
   - F8 para eliminar línea
   - F5 para añadir nueva línea
   - F10 para guardar
4. **Confirmación** → Documento actualizado

---

## 🔧 Componentes Nuevos

### **DocumentLinesEditor.php**
Editor completo de líneas de documento con:
- Navegación por cursores
- Añadir/Editar/Eliminar líneas
- Confirmación de eliminación
- Cálculo automático de totales

---

## 📝 Archivos Modificados

1. ✅ **DocumentLinesEditor.php** (NUEVO)
   - Editor de líneas con tabla
   - Navegación y edición completa

2. ✅ **DocumentosActions.php**
   - Método `editar()` actualizado
   - Flujo de 2 pasos (datos + líneas)

3. ✅ **LineItemModal.php**
   - Corregido BACKSPACE con referencia
   - Reseteo de campos al inicio

---

## 🎯 Beneficios

### Para el Usuario
- ✅ **Control total** sobre las líneas del documento
- ✅ **Visualización clara** de todas las líneas
- ✅ **Edición rápida** con cursores
- ✅ **Seguridad** con confirmación de eliminación
- ✅ **Cálculos automáticos** de totales

### Para el Sistema
- ✅ **Código modular** y reutilizable
- ✅ **Interfaz consistente** con el resto del sistema
- ✅ **Fácil de extender** (ordenar, buscar, etc.)

---

## 🚀 Próximas Mejoras Sugeridas

### Corto Plazo
- [ ] Reordenar líneas (mover arriba/abajo)
- [ ] Duplicar línea
- [ ] Búsqueda/filtrado de líneas

### Medio Plazo
- [ ] Editar descripción de línea
- [ ] Cambiar producto de una línea
- [ ] Importar líneas desde otro documento

### Largo Plazo
- [ ] Plantillas de líneas
- [ ] Sugerencias inteligentes
- [ ] Validación de stock en tiempo real

---

**Versión**: 1.2.0  
**Fecha**: 2026-01-13  
**Estado**: ✅ Implementado y Funcional
