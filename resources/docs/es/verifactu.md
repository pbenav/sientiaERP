# Veri*Factu — Configuración y Cumplimiento

Esta guía explica en detalle cómo configurar y operar la integración de sientiaERP con el sistema **Veri\*Factu** de la AEAT.

## ¿Qué es Veri*Factu?

Veri\*Factu (Sistema de Verificación de Facturas) es la nueva normativa de facturación impuesta por la AEAT en España. Obliga a los sistemas informáticos de facturación a:

- Generar un **hash encadenado** e inalterable para cada factura.
- Garantizar la **trazabilidad** de todos los registros.
- Optionalmente, enviar los registros en **tiempo real** a la plataforma de la AEAT.

> **Marco legal:** Reglamento aprobado por el Real Decreto 1007/2023, de 5 de diciembre (BOE-A-2023-24840).

---

## Arquitectura del Sistema

sientiaERP implementa Veri\*Factu según las especificaciones técnicas de la AEAT:

### Estructura del Hash

Cada registro de factura incluye:

```
IDFactura = NIF + NumSerie + FechaExpedicion
HashPrevio = SHA-256(registro anterior) 
HashActual = SHA-256(Huella + FechaHoraHuella + Software)
```

El encadenamiento garantiza que **ningún registro puede modificarse** sin invalidar todos los posteriores.

### Tipos de Registro

| Tipo | Descripción |
|---|---|
| `RegistroAlta` | Factura nueva |
| `RegistroAnulacion` | Anulación de una factura |

---

## Configuración Paso a Paso

### 1. Obtener el Certificado Digital

Necesitas un certificado electrónico reconocido por la AEAT:
- Certificado de **Representante de Persona Jurídica** (para empresas)
- Certificado de **Persona Física** (para autónomos)

Puedes obtenerlo en la FNMT: [https://www.fnmt.es](https://www.fnmt.es)

### 2. Instalar el Certificado en sientiaERP

1. Exporta el certificado en formato **PKCS#12** (`.p12` o `.pfx`) desde tu navegador o sistema operativo.
2. En sientiaERP, accede a **Configuración → Certificado Digital**.
3. Sube el archivo y proporciona la contraseña.
4. Pulsa **Validar Certificado**. El sistema mostrará el titular y la fecha de caducidad.

### 3. Configurar el Modo de Operación

Desde **Configuración → Veri\*Factu**:

- **Modo Pruebas**: Registra localmente sin enviar a la AEAT. Ideal para verificar la configuración.
- **Modo Producción**: Envía en tiempo real a la AEAT.

> Activa el **Modo Producción** solo cuando hayas verificado que todo funciona correctamente en el Modo Pruebas.

---

## Operativa Diaria

### Emisión de Facturas

El proceso es completamente transparente para el usuario:

1. Crea la factura normalmente desde **Facturas → Nueva Factura**.
2. Al pulsar **Emitir**, el sistema automáticamente:
   - Genera el XML del registro de factura.
   - Calcula el hash encadenado con la factura anterior.
   - Firma el registro con el certificado digital.
   - (Modo Producción) Envía el registro a la AEAT y almacena la respuesta.
3. La factura queda **bloqueada e inalterable**.

### Consultar el Estado de un Registro

Desde la ficha de cualquier factura emitida, en la pestaña **Veri\*Factu**, puedes ver:
- El `IDFactura`
- El `Hash` generado
- El estado del envío a la AEAT
- La respuesta de la AEAT (código y descripción)

---

## Resolución de Errores

### Errores Comunes de la AEAT

| Código | Descripción | Solución |
|---|---|---|
| `1101` | NIF no identificado | Verifica el NIF en Configuración |
| `1102` | Certificado no válido | Renueva o reinstala el certificado |
| `2001` | Error en el formato XML | Contacta con soporte técnico |
| `3001` | Factura ya registrada | La factura ya existe en la AEAT, no hay acción necesaria |

---

## Documentación Oficial de la AEAT

Para más información, consulta los documentos técnicos oficiales disponibles en `/docs/` de la instalación:

- Especificaciones técnicas del formato XML
- Guía de validaciones y errores
- Esquemas XSD para validación
