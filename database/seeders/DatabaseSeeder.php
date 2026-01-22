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
            ProductSeeder::class,
            TerceroSeeder::class,      // ANTES de SettingSeeder para que CLIPOS exista
            SettingSeeder::class,      // DESPUÉS de TerceroSeeder
            AiSettingsSeeder::class,   // Ajustes de IA
            DocumentoSeeder::class,
        ]);
    }
}
