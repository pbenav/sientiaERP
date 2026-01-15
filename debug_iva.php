<?php
use App\Models\Impuesto;
use App\Models\Product;

$impuestos = Impuesto::where('tipo', 'iva')->where('activo', true)->pluck('nombre', 'valor');
echo "Impuestos (Value => Name):\n";
foreach ($impuestos as $val => $name) {
    echo "Key: '$val' (Type: " . gettype($val) . ") => '$name'\n";
}

$product = Product::first();
if ($product) {
    echo "\nProduct '{$product->name}':\n";
    echo "Tax Rate: '{$product->tax_rate}' (Type: " . gettype($product->tax_rate) . ")\n";
}
