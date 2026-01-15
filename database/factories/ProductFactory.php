<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $categories = [
            'Electrónica', 'Informática', 'Periféricos', 'Componentes', 
            'Mobiliario', 'Accesorios', 'Cables', 'Almacenamiento',
            'Audio', 'Video', 'Redes', 'Seguridad'
        ];
        
        $prefixes = [
            'LAP', 'MON', 'KBD', 'MOU', 'DSK', 'CHR', 'CAB', 'SSD',
            'RAM', 'CPU', 'GPU', 'PSU', 'CASE', 'FAN', 'AUD', 'VID',
            'NET', 'RTR', 'SWI', 'CAM', 'MIC', 'SPK', 'USB', 'HDD'
        ];

        $products = [
            'Laptop', 'Monitor', 'Teclado', 'Ratón', 'Escritorio',
            'Silla', 'Cable HDMI', 'SSD', 'Disco Duro', 'Memoria RAM',
            'Procesador', 'Tarjeta Gráfica', 'Fuente Alimentación', 'Caja PC',
            'Ventilador', 'Auriculares', 'Webcam', 'Router', 'Switch',
            'Micrófono', 'Altavoces', 'Hub USB', 'Adaptador', 'Soporte Monitor'
        ];

        $adjectives = [
            'Pro', 'Ultra', 'Premium', 'Básico', 'Avanzado', 'Compacto',
            'Inalámbrico', 'Ergonómico', 'Gaming', 'Profesional', 'Portátil',
            'De Alto Rendimiento', 'Económico', 'Silencioso', 'RGB', 'Negro',
            'Blanco', 'Plateado', 'Modular', 'Mecánico'
        ];

        $productName = fake()->randomElement($products) . ' ' . fake()->randomElement($adjectives);
        $sku = fake()->randomElement($prefixes) . fake()->unique()->numberBetween(1000, 9999);
        
        return [
            'sku' => $sku,
            'name' => $productName,
            'description' => fake()->sentence(10),
            'price' => fake()->randomFloat(2, 5, 2000),
            'tax_rate' => fake()->randomElement([0, 4, 10, 21]),
            'stock' => fake()->numberBetween(0, 500),
            'barcode' => fake()->unique()->ean13(),
        ];
    }
}
