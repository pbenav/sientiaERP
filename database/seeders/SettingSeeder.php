<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Setting::set('currency_position', 'suffix', 'Posición del Símbolo de Moneda', 'Localización');
        \App\Models\Setting::set('currency_symbol', '€', 'Símbolo de Moneda', 'Localización');
        \App\Models\Setting::set('language', 'es', 'Idioma del Sistema', 'General');
        \App\Models\Setting::set('presupuesto_validez_dias', 15, 'Validez de Presupuestos (días)', 'Ventas');
        
        \App\Models\Setting::set('pdf_header_html', '<strong>Sientia SL</strong><br>NIF: B12345678<br>Calle Falsa 123, 28001 Madrid<br>Email: info@sientia.com | Tel: 912 345 678', 'Cabecera PDF (HTML)', 'Documentos');
        \App\Models\Setting::set('pdf_footer_text', 'nexERP System | Sientia SL | Registro Mercantil de Madrid, Tomo 12345, Folio 123, Sección 8, Hoja M-123456', 'Pie de página PDF', 'Documentos');
        
        // Cliente por defecto para POS (buscar CLIPOS creado en TerceroSeeder)
        $clientePOS = \App\Models\Tercero::where('codigo', 'CLIPOS')->first();
        if ($clientePOS) {
            \App\Models\Setting::set('pos_default_tercero_id', $clientePOS->id, 'Cliente por Defecto POS', 'POS');
        }
    }
}
