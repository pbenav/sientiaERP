<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Descuento;

class DescuentoSeeder extends Seeder
{
    public function run(): void
    {
        Descuento::firstOrCreate(
            ['nombre' => 'Sin Descuento'],
            ['valor' => 0.00, 'es_predeterminado' => true, 'activo' => true]
        );

        Descuento::firstOrCreate(
            ['nombre' => 'Descuento Comercial 5%'],
            ['valor' => 5.00, 'es_predeterminado' => false, 'activo' => true]
        );

        Descuento::firstOrCreate(
            ['nombre' => 'Cliente VIP 10%'],
            ['valor' => 10.00, 'es_predeterminado' => false, 'activo' => true]
        );
    }
}
