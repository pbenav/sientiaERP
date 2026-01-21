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

        // Obtener formas de pago comunes
        $fpContado = \App\Models\FormaPago::where('tipo', 'contado')->first();
        $fpTransferencia = \App\Models\FormaPago::where('tipo', 'transferencia')->first();
        $fpGiro = \App\Models\FormaPago::where('tipo', 'giro')->first();

        $clientes = [
            [
                'codigo' => 'CLI0001',
                'nombre_comercial' => 'Sientia Soft',
                'razon_social' => 'Sientia Software S.L.',
                'nif_cif' => 'B12345678',
                'email' => 'info@sientia.com',
                'telefono' => '912345678',
                'movil' => '600112233',
                'web' => 'https://www.sientia.com',
                'persona_contacto' => 'Juan Pérez',
                'direccion_fiscal' => 'Calle Mayor 1, Planta 4, Oficina B',
                'codigo_postal_fiscal' => '28001',
                'poblacion_fiscal' => 'Madrid',
                'provincia_fiscal' => 'Madrid',
                'pais_fiscal' => 'España',
                'iban' => 'ES9800001111222233334444',
                'banco' => 'Banco Santander',
                'bic' => 'BSCHESMM', // O swift
                'forma_pago_id' => $fpTransferencia?->id,
                'observaciones' => 'Cliente preferente. Horario de recepción de facturas: L-V 9:00 a 14:00.',
            ],
            [
                'codigo' => 'CLI0002',
                'nombre_comercial' => 'Supermercados Paco',
                'razon_social' => 'Francisco García S.A.',
                'nif_cif' => 'A87654321',
                'email' => 'paco@pacosuper.com',
                'telefono' => '934567890',
                'persona_contacto' => 'Paco García',
                'direccion_fiscal' => 'Avda. Diagonal 50, Bajos',
                'codigo_postal_fiscal' => '08001',
                'poblacion_fiscal' => 'Barcelona',
                'provincia_fiscal' => 'Barcelona',
                'pais_fiscal' => 'España',
                'direccion_envio_diferente' => true,
                'direccion_envio' => 'Polígono Industrial Zona Franca, Calle A, Nave 23',
                'codigo_postal_envio' => '08040',
                'poblacion_envio' => 'Barcelona',
                'provincia_envio' => 'Barcelona',
                'pais_envio' => 'España',
                'forma_pago_id' => $fpGiro?->id,
                'dias_pago' => 30,
                'limite_credito' => 5000.00,
            ],
            [
                'codigo' => 'CLI0003',
                'nombre_comercial' => 'Construcciones El Muro',
                'razon_social' => 'Construcciones y Reformas Integrales El Muro S.L.',
                'nif_cif' => 'B55443322',
                'email' => 'obras@elmuro.es',
                'telefono' => '954321098',
                'direccion_fiscal' => 'Calle Ladrillo 4, Sevilla',
                'codigo_postal_fiscal' => '41001',
                'poblacion_fiscal' => 'Sevilla',
                'provincia_fiscal' => 'Sevilla',
                'pais_fiscal' => 'España',
                'forma_pago_id' => $fpContado?->id,
            ],
            // Cliente Test Overflow
            [
                'codigo' => 'CLITEST',
                'nombre_comercial' => 'Empresa con Nombre Extremadamente Largo S.A.U. para Pruebas de Desbordamiento en Documentos PDF y Pantallas',
                'razon_social' => 'Sociedad Anónima Unipersonal de Pruebas de Estrés Visual y Tipográfico con Denominación Kilométrica Inscrita en el Registro Mercantil de Villaenmedio Tomo I Libro II Folio III',
                'nif_cif' => 'A99999999X',
                'email' => 'departamento.de.administracion.y.finanzas.super.largo@dominio-extremadamente-largo-para-pruebas.com',
                'telefono' => '9001234567890123',
                'direccion_fiscal' => 'Avenida de la Constitución de 1812, Número 452, Bloque 3, Escalera B, 4º Planta, Puerta Izquierda, Urbanización Los Rosales del Monte',
                'codigo_postal_fiscal' => '12345',
                'poblacion_fiscal' => 'San Pedro del Pinatar de los Vinos',
                'provincia_fiscal' => 'Castellón de la Plana',
                'pais_fiscal' => 'España',
                'observaciones' => 'Este cliente existe únicamente para comprobar que los layouts de facturas, albaranes y otros documentos son capaces de manejar textos inusualmente largos sin romperse, solaparse ni causar errores de renderizado. Por favor, verificar atentamente los saltos de línea y el ajuste de texto.',
            ]
        ];

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
                'razon_social' => 'Global Supply Chain International Solutions S.A.',
                'nif_cif' => 'A99999999',
                'email' => 'ventas@globalsupply.com',
                'telefono' => '911111111',
                'direccion_fiscal' => 'Polígono Industrial Las Arenas, Nave 55-58',
                'codigo_postal_fiscal' => '28800',
                'poblacion_fiscal' => 'Alcalá de Henares',
                'provincia_fiscal' => 'Madrid',
                'pais_fiscal' => 'España',
                'iban' => 'ES1122223333444455556666',
                'banco' => 'BBVA',
                'forma_pago_id' => $fpTransferencia?->id,
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
                'pais_fiscal' => 'España',
                'forma_pago_id' => $fpGiro?->id,
                'dias_pago' => 60,
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
            'direccion_fiscal' => 'Calle del Transporte 1',
            'codigo_postal_fiscal' => '28000',
            'poblacion_fiscal' => 'Madrid',
            'provincia_fiscal' => 'Madrid',
            'forma_pago_id' => $fpTransferencia?->id,
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
