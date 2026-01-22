<?php

namespace Database\Seeders;

use App\Models\TipoTercero;
use Illuminate\Database\Seeder;

class TipoTerceroSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'codigo' => 'CLI',
                'nombre' => 'Cliente',
                'descripcion' => 'Cliente habitual',
            ],
            [
                'codigo' => 'PRO',
                'nombre' => 'Proveedor',
                'descripcion' => 'Proveedor de mercancía o servicios',
            ],
            [
                'codigo' => 'EMP',
                'nombre' => 'Empleado',
                'descripcion' => 'Trabajador de la empresa',
            ],
            [
                'codigo' => 'TRA',
                'nombre' => 'Transportista',
                'descripcion' => 'Empresa de transporte',
            ],
            [
                'codigo' => 'AGE',
                'nombre' => 'Agente Comercial',
                'descripcion' => 'Comercial externo',
            ],
            [
                'codigo' => 'OTRO',
                'nombre' => 'Otro',
                'descripcion' => 'Otros terceros',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoTercero::firstOrCreate(
                ['codigo' => $tipo['codigo']],
                array_merge($tipo, ['activo' => true])
            );
        }
    }
}
