<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Setting::set('pdf_logo_type', 'text', 'Tipo de Logo PDF', 'PDF');
        \App\Models\Setting::set('pdf_logo_text', 'nexERP System', 'Texto del Logo', 'PDF');
        \App\Models\Setting::set('pdf_logo_image', null, 'Imagen del Logo', 'PDF');
    }
}
