<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingSerie;
use App\Models\Impuesto;

class BillingSerieSeeder extends Seeder
{
    public function run(): void
    {
        $ivaDefecto = Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->first();
        $irpfDefecto = Impuesto::where('tipo', 'irpf')->where('es_predeterminado', true)->first();

        BillingSerie::firstOrCreate(
            ['codigo' => 'A'],
            [
                'nombre' => 'Serie General',
                'sujeta_irpf' => false,
                'devenga_iva' => true,
                'activo' => true,
                'iva_defecto_id' => $ivaDefecto?->id,
                'irpf_defecto_id' => $irpfDefecto?->id,
            ]
        );

        BillingSerie::firstOrCreate(
            ['codigo' => 'R'],
            [
                'nombre' => 'Serie Rectificativa',
                'sujeta_irpf' => false,
                'devenga_iva' => true,
                'activo' => true,
                'iva_defecto_id' => $ivaDefecto?->id,
                'irpf_defecto_id' => $irpfDefecto?->id,
            ]
        );
    }
}
