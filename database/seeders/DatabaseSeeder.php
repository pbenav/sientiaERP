<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ImpuestoSeeder::class,
            DescuentoSeeder::class,
            BillingSerieSeeder::class,
            FormaPagoSeeder::class,
            UserSeeder::class,
            TipoTerceroSeeder::class,
            MasterTerceroSeeder::class, // Solo CLIPOS
            SettingSeeder::class,       // Carga settings usando CLIPOS
            SettingsSeeder::class,
            AiSettingsSeeder::class,
            LabelFormatSeeder::class,
        ]);
    }
}
