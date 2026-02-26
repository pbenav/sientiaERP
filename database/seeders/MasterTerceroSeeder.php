<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Models\FormaPago;

class MasterTerceroSeeder extends Seeder
{
    public function run(): void
    {
        $tipoCli = TipoTercero::where('codigo', 'CLI')->first();
        $fpContado = FormaPago::where('tipo', 'contado')->first();

        // Cliente especial para POS (contado)
        $clientePOS = [
            'codigo' => 'CLIPOS',
            'nombre_comercial' => 'Cliente de contado para POS',
            'razon_social' => 'Cliente de contado para POS',
            'nif_cif' => '00000000X',
            'email' => 'pos@tienda.local',
            'direccion_fiscal' => 'Venta en Tienda',
            'codigo_postal_fiscal' => '00000',
            'poblacion_fiscal' => 'Local',
            'forma_pago_id' => $fpContado?->id,
            'activo' => true,
        ];
        
        $terceroPOS = Tercero::updateOrCreate(
            ['codigo' => 'CLIPOS'],
            $clientePOS
        );

        if ($tipoCli && !$terceroPOS->tipos()->where('codigo', 'CLI')->exists()) {
            $terceroPOS->tipos()->attach($tipoCli->id);
        }
        
        $this->command->info('✓ Cliente CLIPOS creado/actualizado.');
    }
}
