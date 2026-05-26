# Arquitectura Técnica — sientiaERP

Documentación técnica para desarrolladores y administradores de sistema que necesiten entender la estructura interna, extender la plataforma o integrarla con sistemas externos.

---

## 1. Stack Tecnológico

| Componente | Tecnología | Versión |
|---|---|---|
| **Backend** | PHP / Laravel | PHP 8.2+, Laravel 11 |
| **Panel de Admin** | Filament PHP | v3.x |
| **Frontend** | Tailwind CSS + Alpine.js + Vite | Tailwind 3, Alpine 3 |
| **Base de datos** | MySQL / MariaDB | MySQL 8.0+ |
| **Caché / Colas** | Redis | 7.x |
| **PDFs** | DomPDF (barryvdh/laravel-dompdf) | ^3.1 |
| **OCR** | Google Cloud Document AI | API v1 |
| **IA Visión** | Google Gemini | gemini-1.5-flash / pro |
| **IA Texto** | OpenAI / Gemini | GPT-4 / Gemini |
| **Código de barras** | picqer/php-barcode-generator | ^3.2 |
| **QR** | simplesoftwareio/simple-qrcode | ^4.2 |
| **XML Firma** | robrichards/xmlseclibs | ^3.1 |
| **Markdown** | Laravel CommonMark (`Str::markdown`) | Nativo |

---

## 2. Estructura de Directorios

```
sientiaERP/
├── app/
│   ├── Console/           # Comandos Artisan personalizados
│   ├── Filament/
│   │   ├── Pages/         # Páginas personalizadas del panel
│   │   ├── Resources/     # CRUDs del panel (Facturas, Clientes, etc.)
│   │   └── Widgets/       # Widgets del dashboard
│   ├── Http/
│   │   └── Controllers/   # Controladores HTTP (PDFs, Docs, OCR...)
│   ├── Models/            # Modelos Eloquent
│   ├── Observers/         # Observers para lógica automática
│   ├── Providers/
│   │   └── Filament/      # AdminPanelProvider (configuración de Filament)
│   └── Services/          # Servicios de negocio (Verifactu, OCR, etc.)
├── database/
│   ├── migrations/        # Migraciones de base de datos
│   └── seeders/           # Datos iniciales (impuestos, series, etc.)
├── public/
│   └── build/             # Assets compilados por Vite (no editar)
├── resources/
│   ├── css/
│   │   ├── app.css        # Estilos base (Tailwind)
│   │   └── filament/      # Tema personalizado del panel
│   ├── docs/              # ← Esta documentación (Markdown)
│   │   ├── es/            # Documentación en español
│   │   └── en/            # Documentación en inglés
│   ├── js/                # JavaScript frontend
│   └── views/             # Vistas Blade
└── routes/
    ├── web.php            # Rutas web (PDFs, documentación, TPV)
    └── api.php            # API REST (si aplica)
```

---

## 3. Modelos Principales

### 3.1 Documento (Factura, Albarán, Presupuesto, Pedido)

El modelo central es `Documento`, que representa cualquier documento del ciclo de venta. Utiliza un campo `tipo` para diferenciar:

| Tipo | Descripción |
|---|---|
| `factura` | Factura de venta ordinaria |
| `rectificativa` | Factura rectificativa |
| `proforma` | Factura proforma |
| `presupuesto` | Presupuesto/oferta |
| `pedido` | Pedido de venta |
| `albaran` | Albarán de entrega |

Cada `Documento` tiene muchas `DocumentoLinea` (líneas de detalle) y pertenece a un `Tercero` (cliente).

### 3.2 Tercero (Cliente / Proveedor)

El modelo `Tercero` representa tanto clientes como proveedores. El campo `tipo_tercero` (o la relación con `TipoTercero`) determina si es cliente, proveedor o ambos.

### 3.3 Product

Representa artículos del catálogo. Tiene relaciones con:
- `ProductPurchaseHistory`: historial de precios de compra por proveedor
- `DocumentoLinea`: líneas de documentos de venta y compra

### 3.4 Ticket (TPV)

El modelo `Ticket` representa una venta en el TPV, diferenciado de las facturas normales. Contiene `TicketItem` como líneas de venta y pertenece a una `CashSession`.

### 3.5 ImportDraft (Borradores OCR)

Almacena los documentos procesados por OCR en estado borrador, pendientes de confirmación por el usuario. Contiene el JSON con los datos extraídos y el fichero original.

---

## 4. Observers y Automatizaciones

Los `Observer` de Eloquent manejan la lógica de negocio que debe ejecutarse automáticamente al crear/actualizar modelos:

| Observer | Responsabilidad |
|---|---|
| `DocumentoObserver` | Numeración automática, cálculo de totales, encadenamiento Veri*Factu |
| `ProductObserver` | Actualización de precios de coste tras albaranes de compra |
| `CashSessionObserver` | Cálculo de diferencias de caja al cerrar |

---

## 5. Servicios Principales

### 5.1 VerifactuService

Gestiona el cumplimiento con la normativa Veri\*Factu de la AEAT:

- Generación del XML del registro de factura según el esquema oficial
- Cálculo del **hash SHA-256 encadenado** con la factura anterior
- Firma digital del XML con el certificado PKCS#12 (usando `robrichards/xmlseclibs`)
- Envío a la plataforma de la AEAT en Modo Producción
- Almacenamiento de la respuesta de la AEAT (código, timestamp, CSV)

### 5.2 OcrImportService

Coordina el flujo de reconocimiento automático de documentos de compra:

1. **Preprocesado**: Convierte PDF a imágenes si es necesario
2. **Document AI**: Envía el documento a Google Cloud Document AI y procesa la respuesta
3. **Gemini Vision** (fallback): Si Document AI no extrae suficientes datos, usa Gemini como modelo secundario
4. **Normalización**: Mapea los campos extraídos al esquema de `ImportDraft`
5. **Matching de terceros**: Intenta identificar automáticamente al proveedor por NIF o nombre

### 5.3 PdfService / PdfController

Genera los documentos PDF usando DomPDF con plantillas Blade específicas:
- Factura de venta
- Albarán de venta
- Presupuesto
- Ticket TPV (formato ticket térmico 80mm)
- Ticket de regalo (sin precios)
- Etiquetas de producto

---

## 6. Sistema de Rutas

Las rutas están organizadas en `routes/web.php`:

```php
// Documentos PDF (con auth)
GET /documentos/{record}/pdf          → PdfController@downloadDocumento
GET /documentos/{record}/ticket       → PdfController@ticketDocumento

// TPV Tickets
GET /pos/ticket/{record}              → PdfController@ticketPos
GET /pos/ticket-raw/{record}          → PdfController@ticketPosRaw
GET /pos/ticket-regalo/{record}       → PdfController@ticketRegalo

// Etiquetas
GET /etiquetas/{record}/pdf           → LabelController@download

// FacturaE (XML para AAPP)
GET /facturae/{record}/download       → FacturaeController@download

// Documentación (esta sección)
GET /documentacion/{slug?}            → DocumentationController@index
```

El panel de administración Filament está montado en `/admin`.

---

## 7. Variables de Entorno Clave

```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sientia_erp
DB_USERNAME=...
DB_PASSWORD=...

# Caché y colas
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# Email
MAIL_MAILER=smtp
MAIL_HOST=...

# Google Cloud / Document AI
GOOGLE_CLOUD_PROJECT=...
GOOGLE_APPLICATION_CREDENTIALS=/ruta/al/service-account.json

# Google Gemini
GEMINI_API_KEY=...

# OpenAI (opcional)
OPENAI_API_KEY=...

# Configuración de la app
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Europe/Madrid
```

---

## 8. Extensión del Sistema

### 8.1 Añadir un Nuevo Recurso Filament

```bash
php artisan make:filament-resource NombreModelo --generate
```

Esto crea automáticamente el Resource, las páginas List/Create/Edit y el formulario con los campos del modelo.

### 8.2 Añadir Documentación

Para añadir nuevos documentos a este portal:

1. Crea el fichero `.md` en `resources/docs/es/mi-documento.md`
2. Añade la entrada en el array `$menu` de `DocumentationController`:
   ```php
   'mi-documento' => 'Mi Nuevo Documento',
   ```
3. El documento estará disponible automáticamente en `/documentacion/mi-documento`.

### 8.3 Personalizar Plantillas PDF

Las plantillas PDF están en `resources/views/pdf/`. Usa HTML + CSS estándar. DomPDF tiene limitaciones con Flexbox/Grid; usa tablas HTML para layouts complejos.
