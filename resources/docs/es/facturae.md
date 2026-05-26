# Factura Electrónica (FacturaE) — sientiaERP

sientiaERP genera facturas electrónicas en formato **FacturaE 3.2.2**, el estándar XML obligatorio para la facturación con las Administraciones Públicas en España (AAPP), y recomendado para cualquier empresa que quiera adoptar la factura electrónica en sus relaciones B2B.

---

## 1. ¿Qué es FacturaE?

**FacturaE** es el formato XML normalizado para la factura electrónica en España, definido por el Ministerio de Hacienda. Es el único formato aceptado por:

- **FACe** (Punto General de Entrada de Facturas Electrónicas del Estado)
- **AEAT** para ciertos procedimientos
- Administraciones Autonómicas y Locales acogidas al sistema
- Empresas privadas que han adoptado la factura electrónica B2B

> **Diferencia clave con Veri\*Factu:** Veri\*Factu es un registro de integridad (hash encadenado) que garantiza que las facturas no se modifican. FacturaE es el **contenedor XML** del documento de factura que se envía al destinatario. Ambos pueden coexistir: una factura puede ser FacturaE y estar registrada en Veri\*Factu simultáneamente.

### Marco Legal

| Normativa | Descripción |
|---|---|
| Ley 25/2013 | Impulso a la factura electrónica con AAPP |
| Real Decreto 1619/2012 | Reglamento de facturación |
| Formato FacturaE v3.2.2 | Esquema XML oficial del Ministerio de Hacienda |
| Ley Crea y Crece (Ley 18/2022) | Obligatoriedad de e-factura B2B (en implantación progresiva) |

---

## 2. Cómo Generar una Factura FacturaE

### 2.1 Desde la Ficha de una Factura

1. Ve a **Ventas → Facturas** y abre la factura que deseas exportar.
2. La factura debe estar en estado **Emitida** (confirmada, con número asignado).
3. En la barra de acciones, pulsa **Descargar FacturaE**.
4. El sistema genera el XML y lo descarga automáticamente como `Facturae_F-2026-00001.xml`.

> **Nota:** El botón de FacturaE solo aparece en facturas de venta y facturas de compra confirmadas. No está disponible en presupuestos, albaranes ni borradores.

### 2.2 Facturación de Compras

Para registrar una factura de proveedor en formato FacturaE (cuando el proveedor te la envía):
1. Ve a **Compras → Facturas de Compra → Nueva**.
2. Si el proveedor te envía el XML FacturaE, puedes importarlo directamente (o bien usar el [OCR](user-manual.md#reconocimiento-automático-de-documentos-ocr) para documentos PDF/imagen).

---

## 3. Firma Digital del XML

El XML de FacturaE generado por sientiaERP incluye **firma digital XAdES-BES** si tienes configurado un certificado digital en el sistema.

### 3.1 ¿Por qué es necesaria la firma?

La firma digital garantiza:
- **Autenticidad**: el XML proviene de quien dice ser
- **Integridad**: el contenido no ha sido modificado tras la firma
- **No repudio**: el emisor no puede negar haber emitido la factura

FACe y la mayoría de portales de AAPP **exigen** que el XML esté firmado con un certificado reconocido.

### 3.2 Configurar el Certificado

sientiaERP usa el **mismo certificado digital** para FacturaE y para Veri\*Factu. Solo necesitas configurarlo una vez:

1. Ve a **Configuración → Certificado Digital**.
2. Sube el fichero `.p12` o `.pfx` de tu certificado.
3. Introduce la contraseña del certificado.
4. Pulsa **Validar Certificado**. Se mostrará el titular y la fecha de caducidad.

Si el certificado está correctamente configurado, todos los XML de FacturaE se firmarán automáticamente con **XAdES-BES** usando `XMLSecLibs`.

> Si no hay certificado configurado, el XML se genera igualmente pero **sin firma**. Algunos portales de AAPP lo rechazarán en ese caso.

---

## 4. Estructura del XML FacturaE 3.2.2

El XML generado sigue fielmente el esquema oficial. Su estructura principal es:

```xml
<facturae:Facturae xmlns:facturae="http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_2.xml"
                   xmlns:ds="http://www.w3.org/2000/09/xmldsig#">

  <FileHeader>              <!-- Versión del formato y modalidad de factura -->
  <Parties>                 <!-- Datos del emisor (tu empresa) y del receptor (cliente) -->
  <Invoices>                <!-- Una o varias facturas -->
    <Invoice>
      <InvoiceHeader>       <!-- Número, serie, tipo de factura, fecha -->
      <InvoiceIssueData>    <!-- Fecha de operación, lugar, moneda, idioma -->
      <TaxesOutputs>        <!-- Impuestos repercutidos (IVA, por tipo) -->
      <TaxesWithheld>       <!-- Retenciones (IRPF) -->
      <InvoiceTotals>       <!-- Totales: base, cuotas, retenciones, total a pagar -->
      <Items>               <!-- Líneas de detalle -->
      <PaymentDetails>      <!-- Forma de cobro e IBAN si procede -->
    </Invoice>
  </Invoices>

  <ds:Signature>            <!-- Firma digital XAdES-BES (si hay certificado) -->

</facturae:Facturae>
```

### Tipos de Factura Soportados

| Código XML | Tipo | Descripción |
|---|---|---|
| `FC` | Factura completa | Factura ordinaria (la más habitual) |
| `FA` | Factura abreviada | Tickets y facturas simplificadas |
| `AF` | Autofactura | Facturas de compra en inversión sujeto pasivo |

---

## 5. Envío a FACe y Portales de AAPP

Una vez generado el XML firmado, debes enviarlo al portal correspondiente de la Administración Pública:

### 5.1 FACe (Administración General del Estado)

1. Accede a [https://face.gob.es](https://face.gob.es) con tu certificado digital.
2. Usa la opción **Enviar Factura** y adjunta el XML generado por sientiaERP.
3. El portal valida el XML y lo reenvía al órgano gestor correspondiente.
4. Puedes consultar el estado de la factura desde el panel de FACe.

### 5.2 Portales Autonómicos y Locales

Cada administración puede tener su propio portal. Los más habituales:

| Comunidad / Entidad | Portal |
|---|---|
| Junta de Andalucía | GIRO (girofacturae.juntadeandalucia.es) |
| Generalitat de Catalunya | e.FACT (efact.gencat.cat) |
| Comunidad de Madrid | FACe (mismo sistema estatal) |
| Diputaciones y Ayuntamientos | Generalmente FACe o plataformas propias |

> Consulta siempre con el organismo receptor qué portal y versión de FacturaE aceptan antes de enviar.

---

## 6. Validación del XML

Antes de enviar el XML a una AAPP, puedes validarlo con herramientas oficiales:

- **Validador oficial de FacturaE**: [http://www.facturae.gob.es/herramientas/Paginas/validadorFacturae.aspx](http://www.facturae.gob.es/herramientas/Paginas/validadorFacturae.aspx)
- **Portal FACe**: también valida el XML al subir

Si el XML tiene errores de esquema, el validador indicará exactamente qué campo falla y en qué línea.

---

## 7. Factura Electrónica B2B (Ley Crea y Crece)

La **Ley 18/2022 (Ley Crea y Crece)** establece la obligación progresiva de emitir y recibir facturas electrónicas en relaciones entre empresas (B2B). El calendario previsto es:

| Empresa | Obligación estimada |
|---|---|
| Facturación > 8 millones €/año | 2025 (pendiente de reglamento definitivo) |
| Resto de empresas y autónomos | 2026 (pendiente de reglamento definitivo) |

> **Estado actual (2026):** La obligatoriedad B2B está pendiente del reglamento de desarrollo. sientiaERP ya es compatible con este escenario: genera FacturaE 3.2.2 y puede recibir e importar facturas XML de proveedores.

---

## 8. Preguntas Frecuentes

**¿FacturaE y Veri\*Factu son lo mismo?**
No. Veri\*Factu es un registro de integridad (hash encadenado en la AEAT). FacturaE es el formato XML del documento de factura. Pueden coexistir en la misma factura.

**¿Puedo enviar el XML a un cliente particular?**
Sí, aunque los particulares no están obligados a aceptarlo. Lo habitual es enviar también el PDF de la factura.

**¿El XML incluye el PDF embebido?**
No por defecto. Si el organismo receptor lo requiere, sientiaERP puede generar un XML con el PDF embebido en Base64 en el campo `Attachments`. Contacta con soporte si lo necesitas.

**¿Qué pasa si mi certificado caduca?**
Las facturas generadas antes de la caducidad son válidas. Las nuevas no podrán firmarse hasta que renueves el certificado y lo subas de nuevo en **Configuración → Certificado Digital**.
