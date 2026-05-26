# Manual de Usuario — sientiaERP

Bienvenido al manual de usuario de **sientiaERP**, la plataforma de gestión empresarial integral diseñada para autónomos y PYMEs españolas. Este manual cubre todos los módulos disponibles: ventas, compras, inventario, TPV, etiquetas y más.

---

## 1. Primeros Pasos

### 1.1 Acceso a la Plataforma

Accede con tu email y contraseña en la página de inicio. Si es tu primer acceso, el administrador habrá enviado un email de invitación con un enlace para establecer tu contraseña.

### 1.2 Configuración Inicial Obligatoria

Antes de emitir cualquier documento, configura los datos fiscales de tu empresa:

1. Ve a **Configuración → Ajustes de Empresa**.
2. Rellena: **Razón Social**, **NIF/CIF**, **Dirección fiscal completa**, **Email**, **Teléfono** y **Logotipo**.
3. Si usas facturación electrónica o Veri\*Factu, sube tu certificado digital en **Configuración → Certificado Digital**.
4. Revisa los tipos de **impuestos** predefinidos en **Configuración → Impuestos** (IVA 21%, 10%, 4%, 0%).
5. Configura las **Series de Facturación** en **Configuración → Series de Numeración**.

> **Importante:** Sin los datos fiscales completos, el sistema bloqueará la emisión de documentos oficiales.

---

## 2. Gestión de Clientes y Proveedores (Terceros)

El módulo **Terceros** centraliza clientes, proveedores y clientes/proveedores simultáneamente.

### 2.1 Crear un Tercero

1. Ve a **Terceros → Nuevo**.
2. Selecciona el **tipo**: Cliente, Proveedor, o ambos.
3. Rellena los datos de identificación: Nombre, NIF/CIF, Email, Teléfono, Dirección.
4. En la pestaña **Fiscal**, configura:
   - Tipo de IVA por defecto (General, Reducido, Superreducido, Exento)
   - Si aplica **Retención de IRPF** (7%, 15%...)
   - Si está sujeto a **Recargo de Equivalencia**
   - **Forma de pago** por defecto (contado, transferencia, 30 días...)
5. Guarda el registro.

### 2.2 Búsqueda y Filtrado

Desde el listado de Terceros puedes filtrar por tipo, localidad, estado (activo/inactivo) y buscar por nombre, NIF o email.

### 2.3 Historial de Documentos

Accediendo a la ficha de cualquier tercero verás un resumen de todos sus documentos: facturas emitidas, presupuestos, albaranes, pedidos de compra y facturas de compra.

---

## 3. Catálogo de Productos y Servicios

### 3.1 Crear un Producto/Servicio

1. Ve a **Catálogo → Nuevo Producto**.
2. Rellena: **Nombre**, **Referencia**, **Descripción**, **Precio de venta** y **Tipo de IVA**.
3. Si es un producto físico (no un servicio), activa el **Control de Stock** e introduce el stock inicial.
4. Configura el **precio de coste** para calcular el margen comercial.
5. Asigna una **categoría** para facilitar la búsqueda.

### 3.2 Control de Stock

El stock se actualiza automáticamente al:
- **Emitir un albarán de venta** (resta stock)
- **Registrar un albarán de compra** (suma stock)

Puedes configurar alertas de **stock mínimo** desde la ficha del producto. El sistema notificará cuando el nivel baje del umbral definido.

### 3.3 Historial de Precios de Compra

sientiaERP mantiene un historial de los precios a los que has comprado cada producto a cada proveedor. Esto permite:
- Ver la evolución del precio de coste
- Tomar decisiones de compra informadas
- Calcular automáticamente el último precio pagado al proveedor

---

## 4. Módulo de Ventas

### 4.1 Flujo Completo de Venta

El proceso de venta en sientiaERP sigue este flujo:

```
Presupuesto → Pedido → Albarán → Factura
```

Puedes entrar en cualquier punto del flujo y convertir un documento en el siguiente con un solo clic. Todos los documentos quedan vinculados y trazables.

### 4.2 Presupuestos

1. Ve a **Ventas → Presupuestos → Nuevo**.
2. Selecciona el cliente (los datos fiscales se autocompletan).
3. Añade líneas de concepto: Producto/Servicio, Cantidad, Precio, Descuento (%).
4. El sistema calcula subtotales, impuestos y total automáticamente.
5. Guarda y **envíalo por email** al cliente directamente desde el sistema.

Una vez aceptado, pulsa **Convertir a Pedido** o **Convertir a Factura** directamente.

### 4.3 Pedidos de Venta

Los pedidos permiten reservar stock y gestionar entregas parciales. Desde un pedido puedes generar uno o varios albaranes a medida que vas sirviendo el producto.

### 4.4 Albaranes de Venta

Los albaranes son documentos de entrega que actualizan el stock del almacén. Puedes:
- Generar el **PDF del albarán** para que el cliente firme la recepción.
- Agrupar varios albaranes en **una sola factura** (facturación consolidada).

### 4.5 Facturas

1. Ve a **Ventas → Facturas → Nueva Factura** (o conviértela desde un albarán/pedido).
2. Revisa los datos del cliente y las líneas.
3. Pulsa **Emitir Factura** para finalizar.

> **Atención:** Una factura emitida queda **bloqueada e inalterable** para cumplir con Veri\*Factu. Si hay un error, emite una [Factura Rectificativa](#facturas-rectificativas).

### 4.6 Facturas Rectificativas

Si necesitas corregir una factura ya emitida:
1. Abre la factura original.
2. Pulsa **Rectificar**.
3. El sistema crea una rectificativa con referencia automática a la original.
4. Modifica las líneas incorrectas y emite la rectificativa.

### 4.7 Recibos

Registra el cobro de tus facturas desde **Ventas → Recibos**. Cada recibo queda vinculado a su factura e indica la forma de cobro (efectivo, transferencia, tarjeta, etc.).

### 4.8 Envío de Documentos por Email

Desde cualquier documento (presupuesto, factura, albarán) puedes enviarlo por email al cliente con un solo clic. El PDF se genera automáticamente y se adjunta al correo.

---

## 5. Módulo de Compras

### 5.1 Flujo Completo de Compra

```
Presupuesto Compra → Pedido Compra → Albarán Compra → Factura Compra
```

### 5.2 Reconocimiento Automático de Documentos (OCR)

sientiaERP incluye un potente sistema de **reconocimiento óptico de caracteres (OCR)** integrado con inteligencia artificial para automatizar la entrada de datos de documentos de compra.

**¿Cómo funciona?**

1. Ve a **Compras → Importar Documento** (OCR).
2. Sube la imagen o PDF del albarán o factura recibida del proveedor (escaneado o fotografiado con el móvil).
3. El sistema analiza el documento con IA y extrae automáticamente:
   - Datos del proveedor (nombre, NIF)
   - Número de documento y fecha
   - Líneas de detalle (referencia, descripción, cantidad, precio, impuestos)
   - Totales y forma de pago
4. Revisa los datos extraídos en el **borrador de importación**. El sistema resalta los campos que requieren confirmación.
5. Acepta o corrige los datos y pulsa **Importar**.

**Formatos soportados:** PDF, JPG, PNG, WEBP, TIFF.

**Modelos de IA utilizados:** Document AI (Google Cloud) y modelos de visión de Google Gemini para documentos complejos o de baja calidad.

> **Consejo:** Cuanto mejor sea la calidad de la imagen, mayor será la precisión del reconocimiento. Escanea con al menos 300 DPI o usa la función de documento de la cámara de tu teléfono.

### 5.3 Pedidos de Compra

Crea pedidos a tus proveedores desde **Compras → Pedidos**. Al recibir la mercancía, convierte el pedido en albarán de compra para actualizar el stock automáticamente.

### 5.4 Albaranes de Compra

Registra las recepciones de mercancía. Al confirmar un albarán de compra:
- El **stock** de los productos se incrementa.
- Se actualiza el **historial de precios de coste** del proveedor.
- Se genera un borrador de **Factura de Compra** para validación.

### 5.5 Facturas de Compra

Registra y controla todas las facturas recibidas de proveedores. Puedes:
- Crearlas manualmente o importarlas mediante OCR.
- Vincularlas a sus pedidos y albaranes de compra correspondientes.
- Generar los **recibos de pago** asociados para llevar el control de cuentas a pagar.

---

## 6. Terminal Punto de Venta (TPV)

El módulo TPV está diseñado para ventas rápidas en mostrador. Está optimizado para pantallas táctiles.

### 6.1 Abrir una Sesión de Caja

1. Ve a **TPV → Nueva Sesión de Caja**.
2. Introduce el **dinero inicial** en caja (apertura).
3. La sesión queda registrada con la fecha y hora de apertura.

### 6.2 Proceso de Venta en TPV

1. Busca el producto por nombre, referencia o código de barras (con lector).
2. Ajusta la cantidad si es necesario.
3. Aplica descuentos sobre líneas o sobre el total.
4. Selecciona la forma de cobro: **Efectivo**, **Tarjeta**, **Mixto**.
5. Si es efectivo, introduce el importe entregado y el sistema calcula el cambio.
6. Pulsa **Cobrar** y el sistema genera el ticket e imprime automáticamente (si hay impresora configurada).

### 6.3 Tickets de Regalo

Desde cualquier ticket puedes generar un **ticket de regalo** sin precios, para incluir en los envíos.

### 6.4 Cierre de Caja

Al cerrar la sesión, introduces el **dinero contado en caja** y el sistema compara con las ventas registradas, mostrando cualquier diferencia.

---

## 7. Módulo de Etiquetas

Genera etiquetas de producto profesionales para impresión en impresoras de etiquetas (Zebra, Brother, etc.) o en hojas A4 con formatos estándar (Avery, etc.).

### 7.1 Crear Formato de Etiqueta

Accede a **Etiquetas → Formatos** y define:
- **Dimensiones** de la etiqueta (alto × ancho en mm)
- **Campos a mostrar**: Nombre, Referencia, Precio, Código de barras (EAN-13, Code128...), QR
- **Tipografía y tamaños**

### 7.2 Imprimir Etiquetas

1. Selecciona uno o varios productos en el catálogo.
2. Pulsa **Generar Etiquetas**.
3. Selecciona el formato de etiqueta y la cantidad por producto.
4. Descarga el PDF listo para imprimir.

---

## 8. Módulo de Expediciones

Gestiona los envíos de mercancía a clientes. Vincula albaranes de venta a expediciones para un control logístico completo. Incluye datos del transportista, número de seguimiento y fecha estimada de entrega.

---

## 9. Informes y Analíticas

Accede a **Informes** para obtener una visión completa del negocio:

| Informe | Descripción |
|---|---|
| Facturación por período | Ventas desglosadas por mes, trimestre o año |
| IVA devengado | Resumen de bases imponibles y cuotas para la declaración trimestral (Modelo 303) |
| IVA soportado | IVA de tus facturas de compra para compensar |
| Clientes por volumen | Ranking de clientes por facturación acumulada |
| Productos más vendidos | Análisis del catálogo por unidades y facturación |
| Beneficio bruto | Comparativa precio de venta vs. coste por producto/período |
| Cuentas a cobrar | Facturas pendientes de cobro por cliente y fecha de vencimiento |
| Cuentas a pagar | Facturas de proveedores pendientes de pago |

---

## 10. Soporte

Si tienes alguna duda, consulta el [Manual de Administración](admin-manual.md) o la [Guía de Instalación](installation.md). Para soporte técnico: **soporte@sientia.com**.
