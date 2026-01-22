# Plan de Implementación Global - Sistema TUI

## ✅ Correcciones Realizadas

### Problema Identificado
El renderizado de la cabecera no era dinámico, causando que el borde derecho (║) se desalineara cuando el título era muy largo o muy corto.

### Solución Implementada
Se ha corregido el cálculo del padding en los tres componentes principales:

1. **ListController.php** ✅
2. **FormController.php** ✅  
3. **Window.php** ✅

**Mejoras:**
- Cálculo dinámico del espacio disponible
- Truncado automático de títulos muy largos
- Padding correcto para centrado perfecto
- Alineación garantizada del borde derecho

---

## 📋 Módulos a Migrar

### Estado Actual

| Módulo | Archivo | Estado | Prioridad |
|--------|---------|--------|-----------|
| **Terceros** | `TercerosActions.php` | ✅ Completado | - |
| **Almacén** | `AlmacenActions.php` | ✅ Completado | - |
| **Documentos** | `DocumentosActions.php` | ⏳ Pendiente | Alta |
| **Detalles** | `DetailsActions.php` | ⏳ Pendiente | Alta |
| **Ventas** | (Varios archivos) | ⏳ Pendiente | Media |

---

## 🎯 Plan de Migración por Módulo

### 1. DocumentosActions.php

**Métodos a migrar:**
- `listarPresupuestos()` → ListController
- `listarPedidos()` → ListController
- `listarAlbaranes()` → ListController
- `listarFacturas()` → ListController
- `crear()` → FormController
- `editar()` → FormController

**Estimación:** 2-3 horas  
**Complejidad:** Media (similar a TercerosActions)

**Pasos:**
1. Identificar estructura de datos de cada tipo de documento
2. Definir columnas para cada lista
3. Implementar callbacks para CRUD
4. Migrar formularios de creación/edición
5. Eliminar código antiguo
6. Probar navegación y validación

---

### 2. DetailsActions.php

**Métodos a migrar:**
- `renderTercero()` → Window o vista personalizada
- `renderProducto()` → Window o vista personalizada
- `renderDocumento()` → Window o vista personalizada

**Estimación:** 1-2 horas  
**Complejidad:** Baja (solo vistas de solo lectura)

**Pasos:**
1. Convertir renderizados manuales a Window
2. Usar campos readonly
3. Implementar navegación con F12
4. Probar visualización

---

### 3. Ventas (Módulos Varios)

**Archivos a revisar:**
- Presupuestos
- Pedidos
- Albaranes
- Facturas
- Recibos

**Estimación:** 4-6 horas  
**Complejidad:** Alta (múltiples módulos interrelacionados)

**Pasos:**
1. Auditar código actual
2. Identificar patrones comunes
3. Crear componentes reutilizables si es necesario
4. Migrar módulo por módulo
5. Probar integración entre módulos

---

## 🔧 Plantilla de Migración

### Para Listas

```php
public function listar(): void
{
    $list = new ListController($this->keyHandler, $this->screen, 'TÍTULO');
    
    // Definir columnas
    $list->setColumns([
        ['field' => 'campo1', 'label' => 'Label 1', 'width' => 15],
        ['field' => 'campo2', 'label' => 'Label 2', 'width' => 30, 'format' => 'currency'],
        // ... más columnas
    ]);
    
    // Callback para obtener datos
    $list->onFetch(function($page, $perPage) {
        return $this->client->getData($page, $perPage);
    });
    
    // Callbacks para acciones
    $list->onCreate(function() {
        $this->crear();
    });
    
    $list->onEdit(function($item) {
        $this->editar($item['id']);
    });
    
    $list->onDelete(function($item) {
        $this->eliminar($item['id']);
    });
    
    $list->onView(function($item) {
        $this->verDetalles($item['id']);
    });
    
    // Ejecutar
    $list->run();
}
```

### Para Formularios

```php
public function crear(): void
{
    $form = new FormController($this->keyHandler, $this->screen, 'NUEVO REGISTRO');
    
    // Añadir campos
    $form->addField('campo1', 'Label 1', '', required: true)
         ->addField('campo2', 'Label 2', '', required: false, validator: function($value) {
             // Validación personalizada
             if (!is_numeric($value)) {
                 return 'Debe ser numérico';
             }
             return null;
         })
         ->addField('campo3', 'Label 3', 'valor_por_defecto');
    
    // Ejecutar formulario
    $values = $form->run();
    
    if ($values) {
        try {
            $this->client->create($values);
            $this->showMessage('Registro creado correctamente', 'success');
        } catch (\Exception $e) {
            $this->showMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}
```

### Para Vistas de Detalles

```php
public function verDetalles(int $id): void
{
    try {
        $data = $this->client->get($id);
        
        $window = new Window($this->screen, 'DETALLES DEL REGISTRO');
        
        $window->addField('campo1', 'Label 1', $data['campo1'], readonly: true)
               ->addField('campo2', 'Label 2', $data['campo2'], readonly: true)
               ->addField('campo3', 'Label 3', $data['campo3'], readonly: true);
        
        $window->setFunctionKeys([
            'F12' => 'Volver'
        ]);
        
        $window->render();
        
        // Esperar tecla para volver
        $this->keyHandler->waitForKey();
        
    } catch (\Exception $e) {
        $this->showMessage('Error: ' . $e->getMessage(), 'error');
    }
}
```

---

## 📊 Métricas Esperadas

### Reducción de Código

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Líneas por lista | ~80-100 | ~30-40 | 60% |
| Líneas por formulario | ~50-60 | ~25-35 | 50% |
| Líneas por vista | ~40-50 | ~20-30 | 50% |
| **Total por módulo** | **~200-250** | **~80-120** | **55%** |

### Beneficios Adicionales

- ✅ Consistencia visual 100%
- ✅ Navegación estandarizada
- ✅ Validación automática
- ✅ Menos bugs
- ✅ Mantenimiento más fácil
- ✅ Desarrollo más rápido

---

## 🚀 Orden de Implementación Recomendado

### Fase 1: Módulos Core (Semana 1)
1. ✅ Terceros (Completado)
2. ✅ Almacén (Completado)
3. ⏳ Documentos (Siguiente)
4. ⏳ Detalles (Siguiente)

### Fase 2: Módulos de Ventas (Semana 2)
5. Presupuestos
6. Pedidos
7. Albaranes
8. Facturas
9. Recibos

### Fase 3: Módulos Adicionales (Semana 3)
10. Otros módulos según necesidad
11. Optimizaciones
12. Testing completo

---

## ✅ Checklist por Módulo

Para cada módulo migrado, verificar:

- [ ] Listas usan ListController
- [ ] Formularios usan FormController
- [ ] Vistas usan Window
- [ ] Navegación con teclas de función funciona
- [ ] Validación de campos funciona
- [ ] Paginación automática funciona
- [ ] Mensajes de error se muestran correctamente
- [ ] Código antiguo eliminado
- [ ] Documentación actualizada
- [ ] Testing realizado

---

## 🎓 Recursos

- **Guía de Desarrollo**: `SISTEMA_TUI.md`
- **Referencia de Teclas**: `TECLAS.md`
- **Inicio Rápido**: `INICIO_RAPIDO.md`
- **Ejemplos**: `TercerosActions.php`, `AlmacenActions.php`

---

## 📝 Notas Importantes

1. **No mezclar sistemas**: Cada módulo debe usar completamente el nuevo sistema o el antiguo, nunca ambos.

2. **Mantener consistencia**: Usar siempre los mismos anchos de columna para campos similares.

3. **Validación**: Implementar validadores personalizados para campos críticos.

4. **Mensajes claros**: Usar mensajes descriptivos en errores y confirmaciones.

5. **Testing**: Probar cada módulo completamente antes de pasar al siguiente.

---

## 🎯 Objetivo Final

**Sistema TUI 100% homogéneo** en todos los módulos de sienteERP, con:
- Navegación consistente
- Validación robusta
- Experiencia de usuario profesional
- Código mantenible y escalable

---

**Fecha de inicio**: 2026-01-13  
**Estimación total**: 2-3 semanas  
**Estado**: En progreso (40% completado)
