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
        // Logo y PDFs
        \App\Models\Setting::set('pdf_logo_type', 'text', 'Tipo de Logo PDF', 'PDF');
        \App\Models\Setting::set('pdf_logo_text', 'sienteERP System', 'Texto del Logo', 'PDF');
        \App\Models\Setting::set('pdf_logo_image', null, 'Imagen del Logo', 'PDF');
        
        // Moneda y formato
        \App\Models\Setting::set('currency_symbol', '€', 'Símbolo de Moneda', 'Moneda');
        \App\Models\Setting::set('currency_position', 'suffix', 'Posición del Símbolo', 'Moneda');
        \App\Models\Setting::set('decimal_separator', ',', 'Separador Decimal', 'Formato');
        \App\Models\Setting::set('thousands_separator', '.', 'Separador de Miles', 'Formato');
        
        // Localización
        \App\Models\Setting::set('locale', 'es', 'Idioma', 'Localización');
        \App\Models\Setting::set('timezone', 'Europe/Madrid', 'Zona Horaria', 'Localización');
        
        // Visualización
        \App\Models\Setting::set('display_uppercase', 'false', 'Mostrar Todo en Mayúsculas', 'Visualización');
    }
}
