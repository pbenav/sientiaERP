#!/usr/bin/env php
<?php
/**
 * Script de verificación de la funcionalidad de mayúsculas
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;
use App\Models\Tercero;
use App\Models\Product;
use App\Models\FormaPago;
use App\Helpers\TextHelper;

echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║     Verificación de Mayúsculas Automáticas                ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. Verificar que existe la configuración
echo "1️⃣  Verificando configuración...\n";
$setting = Setting::where('key', 'display_uppercase')->first();
if ($setting) {
    echo "   ✅ Configuración encontrada\n";
    echo "   📝 Valor actual: " . ($setting->value === 'true' ? 'ACTIVADO' : 'DESACTIVADO') . "\n\n";
} else {
    echo "   ❌ Configuración NO encontrada\n";
    echo "   💡 Ejecuta: php artisan db:seed --class=SettingsSeeder\n\n";
    exit(1);
}

// 2. Probar TextHelper directamente
echo "2️⃣  Probando TextHelper...\n";
$textoOriginal = "Hola Mundo con ñ y á";
$textoFormateado = TextHelper::formatText($textoOriginal);
echo "   Original: $textoOriginal\n";
echo "   Formateado: $textoFormateado\n";
echo "   Estado: " . ($setting->value === 'true' ? 'MAYÚSCULAS' : 'normal') . "\n\n";

// 3. Probar con un tercero real
echo "3️⃣  Probando con modelo Tercero...\n";
$tercero = Tercero::first();
if ($tercero) {
    echo "   Tercero encontrado: ID {$tercero->id}\n";
    echo "   Nombre comercial: {$tercero->nombre_comercial}\n";
    echo "   Razón social: {$tercero->razon_social}\n\n";
} else {
    echo "   ⚠️  No hay terceros en la base de datos\n\n";
}

// 4. Probar con un producto real
echo "4️⃣  Probando con modelo Product...\n";
$product = Product::first();
if ($product) {
    echo "   Producto encontrado: ID {$product->id}\n";
    echo "   Nombre: {$product->name}\n";
    echo "   Descripción: " . ($product->description ?? 'N/A') . "\n\n";
} else {
    echo "   ⚠️  No hay productos en la base de datos\n\n";
}

// 5. Probar con forma de pago
echo "5️⃣  Probando con modelo FormaPago...\n";
$formaPago = FormaPago::first();
if ($formaPago) {
    echo "   Forma de pago encontrada: ID {$formaPago->id}\n";
    echo "   Nombre: {$formaPago->nombre}\n";
    echo "   Descripción: " . ($formaPago->descripcion ?? 'N/A') . "\n\n";
} else {
    echo "   ⚠️  No hay formas de pago en la base de datos\n\n";
}

// 6. Instrucciones para cambiar la configuración
echo "═══════════════════════════════════════════════════════════\n\n";
echo "📋 Para cambiar la configuración:\n\n";
echo "   1. Accede al panel de administración\n";
echo "   2. Ve a Configuración → Ajustes Avanzados\n";
echo "   3. Busca 'Mostrar Todo en Mayúsculas'\n";
echo "   4. Cambia el valor a 'Sí' o 'No'\n";
echo "   5. Guarda los cambios\n\n";

echo "💡 O ejecuta este comando SQL:\n";
echo "   UPDATE settings SET value='true' WHERE key='display_uppercase';\n";
echo "   UPDATE settings SET value='false' WHERE key='display_uppercase';\n\n";

echo "✅ Verificación completada\n\n";
