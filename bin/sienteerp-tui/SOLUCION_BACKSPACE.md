# Solución Temporal para BACKSPACE

Si el BACKSPACE sigue sin funcionar, aquí hay algunas soluciones:

## Opción 1: Ejecutar el Script de Prueba

```bash
cd /home/pablo/Desarrollo/Laravel/sienteERP/bin/sienteerp-tui
php test-keys.php
```

Presiona BACKSPACE y anota el código que muestra. Luego presiona 'q' para salir.

## Opción 2: Verificar Configuración del Terminal

Algunos terminales envían códigos diferentes para BACKSPACE. Prueba:

```bash
# Ver qué envía tu terminal
stty -a | grep erase
```

## Opción 3: Usar DELETE en Lugar de BACKSPACE

Temporalmente, puedes usar la tecla **DELETE** (Supr) que debería funcionar.

## Opción 4: Añadir Más Variantes

Si me dices qué código muestra el test-keys.php, puedo añadir esa variante específica al FunctionKeyMapper.

## Códigos Comunes de BACKSPACE

- `127` (0x7F) - La mayoría de terminales Linux ✅ Ya añadido
- `8` (0x08) - Algunos terminales Windows ✅ Ya añadido  
- `27 91 51 126` (ESC[3~) - DELETE ✅ Ya añadido

---

**¿Qué código te muestra el script de prueba?**
