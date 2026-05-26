# Política de Seguridad, ENS y Cumplimiento SPDX

En **sientiaERP** entendemos que la gestión de la identidad digital (certificados de firma electrónica) y las claves de acceso de Inteligencia Artificial conllevan una gran responsabilidad legal y operativa. Esta guía detalla cómo protegemos la información sensible, las directrices del **Esquema Nacional de Seguridad (ENS)** aplicadas y nuestro compromiso de transparencia a través del estándar **SPDX**.

---

## 1. Protección de Certificados y Claves Privadas

Los certificados digitales (`.p12` o `.pfx`) y sus contraseñas asociadas son el pilar de la firma de facturas para **Veri*Factu** y **Facturae**. Si un tercero no autorizado obtuviera acceso a ellos, podría suplantar legalmente a la empresa ante la Agencia Tributaria.

### 🛡️ Medidas Técnicas de Protección en sientiaERP:

1. **Almacenamiento Aislado de Certificados (Storage Privado):**
   Los archivos de certificados nunca se guardan en el directorio público del servidor. Se almacenan en el disco privado (`local`), que apunta físicamente a `/storage/app/private`. No existe ninguna ruta web ni enlace simbólico que permita descargarlos desde el navegador. Solo el backend PHP del servidor tiene acceso de lectura lógica.

2. **Cifrado AES-256-CBC de Contraseñas (Base de Datos):**
   Las contraseñas de los certificados, las API Keys de Inteligencia Artificial (Gemini y OpenAI) y las credenciales del Service Account de Google Cloud **nunca se guardan en texto plano** en la base de datos.
   * El modelo `Setting` cifra automáticamente estos campos antes de insertarlos en la tabla `settings` utilizando la fachada `Crypt` de Laravel.
   * El cifrado utiliza el estándar industrial **AES-256-CBC** con la clave única `APP_KEY` generada en tu entorno de producción.
   * Al leer la configuración, los datos se desencriptan en caliente en memoria RAM y nunca se exponen en formularios HTML en texto plano (se usan campos enmascarados de tipo contraseña).

3. **Enmascaramiento en Interfaz de Usuario (Filament UI):**
   Los campos sensibles utilizan controles `TextInput::make()->password()->revealable()`, evitando que miradas indiscretas visualicen las claves en la pantalla de administración.

---

## 2. Esquema Nacional de Seguridad (ENS)

El **Esquema Nacional de Seguridad** (Real Decreto 311/2022) regula las condiciones de seguridad que deben cumplir los sistemas de información en España. En el contexto de **sientiaERP**, se aplican los siguientes principios:

### A. Clasificación de la Información (Categoría Media)
La firma digital de facturación e impuestos clasifica el módulo de administración financiera de **sientiaERP** dentro de la **Categoría Media** del ENS. Esto exige:
* **Confidencialidad:** Garantizada mediante el cifrado AES-256-CBC de las credenciales y el aislamiento de ficheros.
* **Integridad:** Las facturas se encadenan criptográficamente (Hash encadenado de Veri*Factu) y se firman con firma XAdES-BES, haciendo imposible su alteración sin invalidar la firma.
* **Trazabilidad:** Cada proceso de firma y envío a la AEAT queda registrado de forma inmutable en el log del sistema, indicando el usuario ejecutor y la marca de tiempo.

### B. Medidas Organizativas y Entorno (Proxmox/Virtualización)
Para sistemas autohospedados (On-Premise o Nube Privada en Proxmox):
1. **Seguridad del Hipervisor:** El Host de Proxmox debe contar con cortafuegos activado, deshabilitar accesos root directos por SSH (usar llaves públicas) y mantener el kernel actualizado.
2. **Cifrado en Reposo:** Se recomienda encarecidamente utilizar sistemas de archivos cifrados (como LUKS o ZFS con cifrado nativo) en los discos físicos del servidor Proxmox.
3. **Copias de Seguridad (Backups):** Las copias de seguridad de las máquinas virtuales o contenedores que alberguen la base de datos de sientiaERP deben ser encriptadas antes de almacenarse en repositorios externos.

---

## 3. SPDX y SBOM (Transparencia en la Cadena de Suministro)

La seguridad del software moderno depende en gran medida de las dependencias externas (paquetes Composer, librerías JS, etc.). Un ataque a la cadena de suministro ocurre cuando una librería de terceros es comprometida.

### 📜 Trazabilidad SPDX y SBOM en sientiaERP:

* **¿Qué es SPDX?**
  **Software Package Data Exchange** (SPDX) es un estándar abierto internacional (ISO/IEC 5962:2021) para documentar metadatos sobre licencias de software, derechos de autor y la composición del código de forma unificada.
  
* **Generación de SBOM (Software Bill of Materials):**
  **sientiaERP** mantiene un inventario de componentes actualizado de forma continua (SBOM). Este inventario documenta de forma transparente todas las dependencias críticas utilizadas para la firma digital y automatizaciones:
  * `robrichards/xmlseclibs`: Librería para la firma criptográfica de XML y certificados.
  * `google/cloud-document-pool`: Para integraciones seguras de IA Documental.
  * Licencia principal de sientiaERP: **AGPL-3.0-only** (Software libre que garantiza la soberanía tecnológica del usuario).

El SBOM en formato SPDX permite realizar análisis automatizados de vulnerabilidades (mediante herramientas como Trivy u OWASP Dependency-Track) para garantizar que ninguna dependencia de terceros contenga fallos conocidos de seguridad antes de su puesta en producción.

---

## 4. Recomendaciones de Oro para el Administrador

1. **Protección de la APP_KEY:**
   Tu clave de cifrado se encuentra en el archivo `.env` (`APP_KEY`). **Nunca compartas este archivo ni lo subas a repositorios de control de versiones públicos**. Si pierdes la `APP_KEY`, no podrás desencriptar las contraseñas guardadas en la base de datos y tendrás que volver a configurarlas.
2. **Uso de HTTPS Obligatorio:**
   Nunca accedas al ERP a través de `http://`. Asegúrate de que tu Apache Proxy en Proxmox tiene configurado de forma estricta el redireccionamiento a **HTTPS (puerto 443)** con certificados SSL válidos y renovados.
3. **Política de Contraseñas de Usuario:**
   Exige contraseñas fuertes (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos) para todas las cuentas con privilegios de Administrador que tengan acceso a la pestaña de **Configuración**.
