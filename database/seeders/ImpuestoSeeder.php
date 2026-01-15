<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Impuesto;

class ImpuestoSeeder extends Seeder
{
    public function run(): void
    {
        // IVA
        Impuesto::firstOrCreate(
            ['nombre' => 'IVA 21%', 'tipo' => 'iva'],
            ['valor' => 21.00, 'es_predeterminado' => true, 'activo' => true]
        );
        
        Impuesto::firstOrCreate(
            ['nombre' => 'IVA 10%', 'tipo' => 'iva'],
            ['valor' => 10.00, 'es_predeterminado' => false, 'activo' => true]
        );
        
        Impuesto::firstOrCreate(
            ['nombre' => 'IVA 4%', 'tipo' => 'iva'],
            ['valor' => 4.00, 'es_predeterminado' => false, 'activo' => true]
        );

        // IRPF
        Impuesto::firstOrCreate(
            ['nombre' => 'IRPF 15%', 'tipo' => 'irpf'],
            ['valor' => 15.00, 'es_predeterminado' => true, 'activo' => true]
        );

        Impuesto::firstOrCreate(
            ['nombre' => 'IRPF 7%', 'tipo' => 'irpf'],
            ['valor' => 7.00, 'es_predeterminado' => false, 'activo' => true]
        );
    }
}
