# Manual de Administración — sientiaERP

Este manual está dirigido a los administradores del sistema responsables de la configuración, seguridad y mantenimiento de la plataforma.

---

## 1. Gestión de Usuarios

### 1.1 Roles del Sistema

sientiaERP implementa un control de acceso basado en roles (**RBAC**). Los roles disponibles son:

| Rol | Descripción |
|---|---|
| **Super Admin** | Acceso total: configuración crítica, usuarios, certificados, logs |
| **Administrador** | Todas las secciones excepto gestión de usuarios y certificados |
| **Comercial** | Ventas: presupuestos, pedidos, albaranes, facturas de venta |
| **Compras** | Módulo de compras: pedidos, albaranes, facturas de proveedores, OCR |
| **Almacén** | Acceso al catálogo y control de stock |
| **Contabilidad** | Solo lectura en todos los módulos + exportación de datos |
| **TPV** | Acceso exclusivo al terminal punto de venta |

### 1.2 Crear un Usuario Nuevo

1. Ve a **Administración → Usuarios → Nuevo**.
2. Rellena nombre, apellidos y email corporativo.
3. Asigna el rol que corresponda.
4. El sistema envía automáticamente un email de invitación con enlace para establecer la contraseña.
5. El enlace caduca en 24 horas; puedes reenviar la invitación desde la ficha del usuario.

### 1.3 Desactivar un Usuario

Para desactivar un usuario (por baja, cambio de empresa, etc.) sin eliminar su historial:
1. Abre la ficha del usuario.
2. Desactiva el toggle **Activo**.
3. El usuario no podrá iniciar sesión pero sus documentos y registros se conservan íntegros.

> **Nota:** Nunca elimines un usuario que tenga documentos asociados. Desactívalo siempre.

### 1.4 Gestión de Contraseñas

Los administradores pueden forzar el restablecimiento de la contraseña de cualquier usuario desde su ficha. El usuario recibirá un email con enlace de restablecimiento.

Para cambiar tu propia contraseña, accede al **menú de usuario** (esquina superior derecha) → **Perfil**.

---

## 2. Configuración General de la Empresa

### 2.1 Datos Fiscales

Accede a **Configuración → Empresa**:

| Campo | Descripción |
|---|---|
| Razón Social | Nombre legal de la empresa |
| NIF/CIF | Número de identificación fiscal |
| Dirección fiscal | Dirección completa que aparece en los documentos |
| Email | Aparece en facturas y presupuestos enviados |
| Teléfono | Información de contacto en documentos |
| Web | Opcional: URL de la empresa |
| Logotipo | PNG/JPG. Se incluye en todas las plantillas PDF |
| IBAN | Número de cuenta para incluir en facturas |

### 2.2 Preferencias de la Aplicación

- **Idioma por defecto**: Español (es) o Inglés (en)
- **Zona horaria**: Europe/Madrid por defecto
- **Formato de fecha**: DD/MM/YYYY
- **Moneda**: Euro (EUR) por defecto
- **Decimales**: Configurable 2-4 decimales en precios

### 2.3 Series de Numeración

Las series de facturación garantizan la numeración secuencial y sin saltos, obligatoria por la normativa española.

Accede a **Configuración → Series de Numeración** para gestionar:

| Serie | Formato ejemplo | Uso |
|---|---|---|
| Facturas de venta | `F-2026-00001` | Facturación ordinaria |
| Facturas rectificativas | `R-2026-00001` | Corrección de facturas emitidas |
| Presupuestos | `P-2026-00001` | Ofertas a clientes |
| Pedidos de venta | `PV-2026-00001` | Órdenes de venta |
| Albaranes de venta | `A-2026-00001` | Documentos de entrega |
| Facturas de compra | `FC-2026-00001` | Registro de compras |

> **Importante:** No cambies el número siguiente de una serie en producción, salvo que estés seguro de que no existen facturas con ese número.

---

## 3. Configuración de Impuestos

### 3.1 Tipos de IVA

Accede a **Configuración → Impuestos** para gestionar los tipos disponibles:

| Tipo | Porcentaje | Aplicación habitual |
|---|---|---|
| IVA General | 21% | La mayoría de productos y servicios |
| IVA Reducido | 10% | Alimentos básicos, hostelería, transporte |
| IVA Superreducido | 4% | Libros, medicamentos, alimentos de primera necesidad |
| IVA Exento | 0% | Exportaciones, servicios médicos, educación |
| Recargo de Equivalencia | 5.2% / 1.4% / 0.5% | Solo para minoristas en régimen especial |

### 3.2 Retención de IRPF

Configura los tipos de retención aplicables a servicios profesionales:
- **7%**: Inicio de actividad (primeros 3 años)
- **15%**: Tipo general para profesionales y artistas
- Tipos personalizados: puedes añadir los que necesites

La retención se descuenta del total de la factura y debe declararse trimestralmente (Modelo 111).

---

## 4. Configuración del OCR e Inteligencia Artificial

### 4.1 Google Cloud Document AI

El módulo de reconocimiento automático de documentos de compra usa **Google Cloud Document AI**. Para activarlo:

1. Crea un proyecto en [Google Cloud Console](https://console.cloud.google.com).
2. Activa la API **Document AI**.
3. Crea un **Procesador** de tipo "Invoice Parser" o "Form Parser" en tu región.
4. Genera una **Cuenta de Servicio** con permisos de Document AI User.
5. Descarga el fichero JSON de credenciales.
6. En sientiaERP, ve a **Configuración → Inteligencia Artificial → Document AI**.
7. Sube el fichero JSON y configura el ID del procesador y la región.
8. Pulsa **Verificar Conexión** para comprobar que todo funciona.

### 4.2 Google Gemini (Visión)

Para documentos complejos, el sistema usa **Google Gemini** como modelo de visión secundario:

1. Ve a **Configuración → Inteligencia Artificial → Gemini**.
2. Introduce tu **API Key** de Google AI Studio (gratuita hasta el límite de cuota).
3. Selecciona el modelo: `gemini-1.5-flash` (rápido y económico) o `gemini-1.5-pro` (máxima precisión).

### 4.3 Revisión de Borradores OCR

Todos los documentos procesados por OCR pasan por un estado de **borrador de importación** antes de confirmarse. Accede a **Compras → Borradores de Importación** para:
- Revisar los datos extraídos
- Corregir campos incorrectos o incompletos
- Confirmar la importación o rechazar el borrador

---

## 5. Veri*Factu — Configuración y Cumplimiento

Consulta la guía completa en [Veri\*Factu](verifactu.md).

### 5.1 Resumen de la Configuración

1. **Certificado Digital**: Sube el `.p12` o `.pfx` en **Configuración → Certificado Digital**.
2. **Modo de operación**: Pruebas o Producción en **Configuración → Veri\*Factu**.
3. **NIF del Software**: Introducir el NIF del fabricante (Sientia) y el nombre del sistema tal como está registrado ante la AEAT.

### 5.2 Monitorización

Accede a **Administración → Logs Veri\*Factu** para ver:
- Estado de envío de cada factura a la AEAT
- Códigos de respuesta y mensajes de error
- Posibilidad de **reenviar** registros fallidos

---

## 6. Formas de Pago

Configura las formas de pago disponibles en **Configuración → Formas de Pago**:

| Código | Nombre | Días de pago |
|---|---|---|
| `CONTADO` | Contado inmediato | 0 días |
| `TRF30` | Transferencia a 30 días | 30 días |
| `TRF60` | Transferencia a 60 días | 60 días |
| `CHEQUE` | Cheque | 0 días |
| `TARJETA` | Tarjeta / Domiciliación | 0 días |

Puedes añadir las que necesites con vencimientos personalizados.

---

## 7. Formatos de Etiquetas

Configura los **formatos de etiquetas** que utilizará tu empresa en **Etiquetas → Formatos**. Cada formato define:

- **Dimensiones** en milímetros (alto × ancho)
- **Campos visibles**: nombre, referencia, precio, EAN-13, QR, logo
- **Número de columnas** por hoja (para hojas A4 con etiquetas múltiples)
- **Impresora de destino** (si hay impresoras configuradas)

---

## 8. Copias de Seguridad

### 8.1 Backup Manual

Accede a **Administración → Backup** para descargar un backup completo que incluye:
- Volcado SQL de la base de datos
- Archivos de la carpeta `storage` (documentos subidos, logs)

### 8.2 Backup Automático Programado

Añade al crontab del servidor:

```bash
* * * * * cd /ruta/sientiaERP && php artisan schedule:run >> /dev/null 2>&1
```

El scheduler ejecuta el backup automático cada noche a las 03:00 y lo almacena en `storage/backups/` con retención de 30 días.

### 8.3 Backup Externo (Recomendado)

Para mayor seguridad, configura el backup en un almacenamiento externo como **Amazon S3** o **Backblaze B2** mediante el driver de almacenamiento de Laravel. Consulta el [Manual Técnico](architecture.md) para la configuración.

---

## 9. Logs y Auditoría

### 9.1 Log de Actividad

Accede a **Administración → Log de Auditoría** para ver un registro completo de:
- Inicio/cierre de sesión de cada usuario
- Creación, modificación y eliminación de registros
- Emisión de documentos fiscales (facturas, rectificativas)
- Cambios en la configuración del sistema

Los registros se conservan **90 días** por defecto (configurable).

### 9.2 Log de Errores del Sistema

Los errores técnicos se registran en `storage/logs/laravel.log`. En producción, configura alertas por email en `.env`:

```env
LOG_CHANNEL=stack
MAIL_FROM_ADDRESS=sistema@tuempresa.com
```

---

## 10. Actualizaciones del Sistema

Para actualizar sientiaERP a una nueva versión:

```bash
# 1. Activar modo mantenimiento
php artisan down

# 2. Obtener los cambios
git pull origin main

# 3. Actualizar dependencias
composer install --no-dev --optimize-autoloader

# 4. Ejecutar migraciones
php artisan migrate --force

# 5. Reconstruir el frontend
npm run build

# 6. Vaciar cachés
php artisan optimize:clear
php artisan optimize

# 7. Desactivar modo mantenimiento
php artisan up
```

Consulta siempre el [Historial de Cambios](changelog.md) antes de actualizar para identificar cambios que puedan requerir atención manual.
