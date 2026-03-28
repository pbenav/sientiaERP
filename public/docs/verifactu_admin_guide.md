# Guía de Configuración Veri*Factu para Administradores

Esta guía detalla los pasos necesarios para configurar y mantener el sistema de cumplimiento fiscal Veri*Factu en SientiaERP.

## 1. Requisitos del Servidor
- **PHP 8.2+** con extensiones `openssl`, `curl` y `soap` activas.
- **Sistema de Colas:** Es imperativo tener un worker de Laravel activo (`php artisan queue:work`) para el envío asíncrono.
- **Certificado Digital:** Un certificado de Sello Electrónico o de Representante en formato `.p12` o `.pfx`.

## 2. Variables de Entorno (.env)
Asegúrese de configurar las siguientes variables para identificar al emisor y al desarrollador:

```env
# NIF del titular del certificado (Emisor de facturas)
VERIFACTU_NIF_EMISOR=B12345678

# NIF del desarrollador del software (Sientia ERP)
# Para pruebas se puede usar el NIF de la AEAT: A46103834
VERIFACTU_NIF_DESARROLLADOR=A46103834

# Modo de operación (test | production)
VERIFACTU_MODE=test
```

## 3. Configuración en el Panel de Control
Acceda a **Configuración > Veri*Factu (Anti-Fraude)**:
- **Activar Veri*Factu:** Debe estar en "SÍ" para iniciar el encadenamiento de facturas.
- **Certificado:** Suba el archivo `.p12` y proporcione la contraseña.
- **Modo de Envío:** Seleccione "Inmediato" para cumplimiento automático o "Manual" para entornos de revisión previa.

## 4. Mantenimiento y Auditoría
- **Encadenamiento:** Cada factura generada se liga a la anterior mediante una huella SHA-256. No elimine registros confirmados, ya que rompería la cadena legal.
- **Anulaciones:** Si anula una factura en el ERP, el sistema enviará automáticamente un registro de anulación a la AEAT. Verifique el estado en la columna "Estado Verifactu".
- **Logs:** En caso de error técnico (4102, 4118), revise `storage/logs/laravel.log` para ver el rastro XML completo.

> [!IMPORTANT]
> El sistema Veri*Factu es de obligado cumplimiento. Una vez pasado a modo `production`, cada factura emitida tiene validez fiscal inmediata ante la AEAT.
