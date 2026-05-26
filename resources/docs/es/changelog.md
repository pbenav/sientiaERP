# Historial de Cambios — sientiaERP

Todos los cambios notables de cada versión se documentan aquí, siguiendo el formato [Keep a Changelog](https://keepachangelog.com/es/1.1.0/).

---

## [2.1.0] — 2026-05

### Añadido
- Integración completa con Veri\*Factu en Modo Producción.
- Sistema de documentación integrado en el panel de administración (este portal).
- Exportación de facturas en formato FacturaE (XML para administraciones públicas).
- Dashboard de analytics con gráficos de facturación mensual vs. año anterior.
- Importación masiva de clientes vía CSV.
- Alertas de stock mínimo por producto.

### Mejorado
- Rendimiento del listado de facturas con más de 10.000 registros (índices optimizados).
- Diseño del PDF de factura: nueva plantilla más profesional.
- Autocompletado de dirección por código postal en el formulario de clientes.

### Corregido
- Error al calcular el recargo de equivalencia en facturas con múltiples tipos de IVA.
- El botón de imprimir factura ya no generaba un PDF en blanco en Safari.

---

## [2.0.0] — 2026-02

### Añadido
- Rediseño completo del panel de administración basado en Filament 3.
- Módulo de Presupuestos y Proformas con conversión automática a factura.
- Gestión de múltiples series de facturación.
- Soporte para facturas rectificativas con referencia automática a la original.
- Control de versiones de configuración: historial de cambios en ajustes críticos.

### Eliminado
- Panel de administración legacy (anterior a Filament). Migración automática de datos.

---

## [1.5.2] — 2025-11

### Corregido
- Vulnerabilidad XSS en el campo de notas de la factura (CVE-2025-XXXX).
- Error de zona horaria en facturas emitidas a las 00:00h locales.

---

## [1.5.0] — 2025-09

### Añadido
- Primer soporte experimental para Veri\*Factu (Modo Pruebas).
- Envío automático de facturas por email al cliente al emitirlas.
- Módulo básico de inventario con control de stock.

### Mejorado
- Tiempo de carga del dashboard reducido en un 60% mediante caché de widgets.
