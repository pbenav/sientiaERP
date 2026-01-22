<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class AiSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // General Provider Settings
        Setting::set('ai_provider', 'google_doc_ai', 'Proveedor IA Principal', 'Automatización IA');
        Setting::set('ai_backup_provider', 'none', 'Proveedor Backup', 'Automatización IA');

        // Gemini
        Setting::set('ai_gemini_api_key', '', 'Gemini API Key', 'Automatización IA');

        // OpenAI
        Setting::set('ai_openai_api_key', '', 'OpenAI API Key', 'Automatización IA');

        // Google Cloud Document AI
        Setting::set('google_application_credentials', '', 'Google Service Account JSON', 'Automatización IA');
        Setting::set('google_project_id', '', 'Google Project ID', 'Automatización IA');
        Setting::set('google_location', 'eu', 'Google Location (us/eu)', 'Automatización IA');
        Setting::set('google_processor_id', '', 'Processor ID (Invoice)', 'Automatización IA');

        // Tesseract
        Setting::set('tesseract_path', '/usr/bin/tesseract', 'Ruta Tesseract Binary', 'Automatización IA');
    }
}
