<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'code' => 'PROD001',
                'name' => 'Laptop HP 15',
                'description' => 'Laptop HP 15 with Intel Core i5, 8GB RAM, 256GB SSD',
                'price' => 699.99,
                'stock' => 10,
            ],
            [
                'code' => 'PROD002',
                'name' => 'Mouse Logitech MX',
                'description' => 'Wireless mouse with ergonomic design',
                'price' => 49.99,
                'stock' => 50,
            ],
            [
                'code' => 'PROD003',
                'name' => 'Keyboard Mechanical',
                'description' => 'RGB Mechanical Keyboard with Cherry MX switches',
                'price' => 89.99,
                'stock' => 25,
            ],
            [
                'code' => 'PROD004',
                'name' => 'Monitor 24"',
                'description' => '24 inch Full HD IPS Monitor',
                'price' => 179.99,
                'stock' => 15,
            ],
            [
                'code' => 'PROD005',
                'name' => 'USB Cable',
                'description' => 'USB-C to USB-A cable, 2 meters',
                'price' => 9.99,
                'stock' => 100,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
