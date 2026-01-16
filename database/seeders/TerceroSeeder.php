<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tercero;
use App\Models\TipoTercero;
use Illuminate\Support\Str;

class TerceroSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'CLI' => TipoTercero::where('codigo', 'CLI')->first(),
            'PRO' => TipoTercero::where('codigo', 'PRO')->first(),
            'EMP' => TipoTercero::where('codigo', 'EMP')->first(),
            'TRA' => TipoTercero::where('codigo', 'TRA')->first(),
        ];

        $clientes = [
            [
                'codigo' => 'CLI0001',
                'nombre_comercial' => 'Sientia Soft',
                'razon_social' => 'Sientia Software S.L.',
                'nif_cif' => 'B12345678',
                'email' => 'info@sientia.com',
                'telefono' => '912345678',
                'direccion_fiscal' => 'Calle Mayor 1, Madrid',
                'codigo_postal_fiscal' => '28001',
                'poblacion_fiscal' => 'Madrid',
                'provincia_fiscal' => 'Madrid',
            ],
            [
                'codigo' => 'CLI0002',
                'nombre_comercial' => 'Supermercados Paco',
                'razon_social' => 'Francisco García S.A.',
                'nif_cif' => 'A87654321',
                'email' => 'paco@pacosuper.com',
                'telefono' => '934567890',
                'direccion_fiscal' => 'Avda. Diagonal 50, Barcelona',
                'codigo_postal_fiscal' => '08001',
                'poblacion_fiscal' => 'Barcelona',
                'provincia_fiscal' => 'Barcelona',
            ],
            [
                'codigo' => 'CLI0003',
                'nombre_comercial' => 'Construcciones El Muro',
                'razon_social' => 'Construcciones El Muro S.L.',
                'nif_cif' => 'B55443322',
                'email' => 'obras@elmuro.es',
                'telefono' => '954321098',
                'direccion_fiscal' => 'Calle Ladrillo 4, Sevilla',
                'codigo_postal_fiscal' => '41001',
                'poblacion_fiscal' => 'Sevilla',
                'provincia_fiscal' => 'Sevilla',
            ]
        ];

        // Cliente especial para POS (contado)
        $clientePOS = [
            'codigo' => 'CLIPOS',
            'nombre_comercial' => 'Cliente de contado para POS',
            'razon_social' => 'Cliente de contado para POS',
            'nif_cif' => '00000000X',
            'email' => 'pos@tienda.local',
            'telefono' => '',
            'direccion_fiscal' => '',
            'codigo_postal_fiscal' => '',
            'poblacion_fiscal' => '',
            'provincia_fiscal' => '',
        ];
        
        $terceroPOS = Tercero::updateOrCreate(
            ['codigo' => 'CLIPOS'],
            $clientePOS
        );
        if (!$terceroPOS->tipos()->where('codigo', 'CLI')->exists()) {
            $terceroPOS->tipos()->attach($tipos['CLI']->id);
        }

        foreach ($clientes as $data) {
            $tercero = Tercero::updateOrCreate(
                ['nif_cif' => $data['nif_cif']],
                $data
            );
            if (!$tercero->tipos()->where('codigo', 'CLI')->exists()) {
                $tercero->tipos()->attach($tipos['CLI']->id);
            }
        }

        $proveedores = [
            [
                'codigo' => 'PRO0001',
                'nombre_comercial' => 'Proveedor Global S.A.',
                'razon_social' => 'Global Supply Chain S.A.',
                'nif_cif' => 'A99999999',
                'email' => 'ventas@globalsupply.com',
                'telefono' => '911111111',
                'direccion_fiscal' => 'Polígono Industrial Las Arenas, Madrid',
                'codigo_postal_fiscal' => '28800',
                'poblacion_fiscal' => 'Alcalá de Henares',
                'provincia_fiscal' => 'Madrid',
            ],
            [
                'codigo' => 'PRO0002',
                'nombre_comercial' => 'Componentes Electrónicos J.S.',
                'razon_social' => 'Componentes J.S. S.L.',
                'nif_cif' => 'B66666666',
                'email' => 'pedidos@jscomponentes.com',
                'telefono' => '932222222',
                'direccion_fiscal' => 'Calle Chip 10, Valencia',
                'codigo_postal_fiscal' => '46001',
                'poblacion_fiscal' => 'Valencia',
                'provincia_fiscal' => 'Valencia',
            ]
        ];

        foreach ($proveedores as $data) {
            $tercero = Tercero::updateOrCreate(
                ['nif_cif' => $data['nif_cif']],
                $data
            );
            if (!$tercero->tipos()->where('codigo', 'PRO')->exists()) {
                $tercero->tipos()->attach($tipos['PRO']->id);
            }
        }

        // Un transportista
        $transpData = [
            'codigo' => 'TRA0001',
            'nombre_comercial' => 'Logística Rápida',
            'razon_social' => 'Logística y Transportes Rápida S.A.',
            'nif_cif' => 'A11223344',
            'email' => 'envios@lograpida.com',
            'telefono' => '913333333',
        ];

        $transp = Tercero::updateOrCreate(
            ['nif_cif' => $transpData['nif_cif']],
            $transpData
        );
        if (!$transp->tipos()->where('codigo', 'TRA')->exists()) {
            $transp->tipos()->attach($tipos['TRA']->id);
        }
    }
}
