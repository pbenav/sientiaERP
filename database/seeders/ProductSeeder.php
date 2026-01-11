<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'LAP001',
                'name' => 'Laptop Pro 15"',
                'description' => 'Laptop de alto rendimiento para desarrolladores',
                'price' => 1200.00,
                'tax_rate' => 21,
                'stock' => 50,
                'barcode' => '1234567890123',
            ],
            [
                'sku' => 'MON001',
                'name' => 'Monitor 4K 27"',
                'description' => 'Monitor con resolución Ultra HD',
                'price' => 350.00,
                'tax_rate' => 21,
                'stock' => 100,
                'barcode' => '2234567890124',
            ],
            [
                'sku' => 'KBD001',
                'name' => 'Teclado Mecánico RGB',
                'description' => 'Teclado para gaming y oficina con switches brown',
                'price' => 85.00,
                'tax_rate' => 21,
                'stock' => 200,
                'barcode' => '3234567890125',
            ],
            [
                'sku' => 'MOU001',
                'name' => 'Ratón Ergonómico',
                'description' => 'Ratón inalámbrico con sensor de alta precisión',
                'price' => 45.00,
                'tax_rate' => 21,
                'stock' => 300,
                'barcode' => '4234567890126',
            ],
            [
                'sku' => 'DSK001',
                'name' => 'Escritorio Elevable',
                'description' => 'Mesa de trabajo ajustable en altura',
                'price' => 450.00,
                'tax_rate' => 21,
                'stock' => 20,
                'barcode' => '5234567890127',
            ],
            [
                'sku' => 'CHR001',
                'name' => 'Silla Ergonómica',
                'description' => 'Silla de oficina con soporte lumbar ajustable',
                'price' => 280.00,
                'tax_rate' => 21,
                'stock' => 40,
                'barcode' => '6234567890128',
            ],
            [
                'sku' => 'CAB001',
                'name' => 'Cable HDMI 2.1',
                'description' => 'Cable HDMI de alta velocidad (2 metros)',
                'price' => 15.00,
                'tax_rate' => 21,
                'stock' => 500,
                'barcode' => '7234567890129',
            ],
            [
                'sku' => 'SSD001',
                'name' => 'Disco SSD 1TB NVMe',
                'description' => 'Unidad de estado sólido ultra rápida',
                'price' => 110.00,
                'tax_rate' => 21,
                'stock' => 150,
                'barcode' => '8234567890130',
            ]
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                $data
            );
        }
    }
}
