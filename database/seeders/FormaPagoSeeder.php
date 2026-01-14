<?php

namespace Database\Seeders;

use App\Models\FormaPago;
use Illuminate\Database\Seeder;

class FormaPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formasPago = [
            [
                'codigo' => 'contado',
                'nombre' => 'Contado',
                'tipo' => 'efectivo',
                'tramos' => json_encode([
                    ['dias' => 0, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Pago al contado (efectivo o inmediato)',
                'activo' => true,
            ],
            [
                'codigo' => 'transferencia',
                'nombre' => 'Transferencia Bancaria',
                'tipo' => 'transferencia',
                'tramos' => json_encode([
                    ['dias' => 0, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Transferencia bancaria inmediata',
                'activo' => true,
            ],
            [
                'codigo' => 'transferencia_30',
                'nombre' => 'Transferencia a 30 días',
                'tipo' => 'transferencia',
                'tramos' => json_encode([
                    ['dias' => 30, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Transferencia bancaria a 30 días',
                'activo' => true,
            ],
            [
                'codigo' => 'transferencia_30_60',
                'nombre' => 'Transferencia 30-60 días',
                'tipo' => 'transferencia',
                'tramos' => json_encode([
                    ['dias' => 30, 'porcentaje' => 50],
                    ['dias' => 60, 'porcentaje' => 50]
                ]),
                'descripcion' => 'Transferencia bancaria en dos pagos: 50% a 30 días y 50% a 60 días',
                'activo' => true,
            ],
            [
                'codigo' => 'transferencia_30_60_90',
                'nombre' => 'Transferencia 30-60-90 días',
                'tipo' => 'transferencia',
                'tramos' => json_encode([
                    ['dias' => 30, 'porcentaje' => 33.33],
                    ['dias' => 60, 'porcentaje' => 33.33],
                    ['dias' => 90, 'porcentaje' => 33.34]
                ]),
                'descripcion' => 'Transferencia bancaria en tres pagos iguales',
                'activo' => true,
            ],
            [
                'codigo' => 'tarjeta',
                'nombre' => 'Tarjeta de Crédito/Débito',
                'tipo' => 'tarjeta',
                'tramos' => json_encode([
                    ['dias' => 0, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Pago con tarjeta de crédito o débito',
                'activo' => true,
            ],
            [
                'codigo' => 'recibo_bancario',
                'nombre' => 'Recibo Bancario',
                'tipo' => 'recibo_bancario',
                'tramos' => json_encode([
                    ['dias' => 30, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Domiciliación bancaria',
                'activo' => true,
            ],
            [
                'codigo' => 'pagare_60',
                'nombre' => 'Pagaré a 60 días',
                'tipo' => 'pagare',
                'tramos' => json_encode([
                    ['dias' => 60, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Pago mediante pagaré a 60 días',
                'activo' => true,
            ],
            [
                'codigo' => 'pagare_90',
                'nombre' => 'Pagaré a 90 días',
                'tipo' => 'pagare',
                'tramos' => json_encode([
                    ['dias' => 90, 'porcentaje' => 100]
                ]),
                'descripcion' => 'Pago mediante pagaré a 90 días',
                'activo' => true,
            ],
        ];

        foreach ($formasPago as $formaPago) {
            FormaPago::updateOrCreate(
                ['codigo' => $formaPago['codigo']],
                array_merge($formaPago, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
