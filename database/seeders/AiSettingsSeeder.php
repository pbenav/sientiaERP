<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class AiSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set(
            'ai_provider', 
            'gemini', 
            'Proveedor IA (Gemini/OpenAI)', 
            'Automatización IA'
        );

        Setting::set(
            'ai_gemini_api_key', 
            '', 
            'Gemini API Key', 
            'Automatización IA'
        );

        Setting::set(
            'ai_openai_api_key', 
            '', 
            'OpenAI API Key', 
            'Automatización IA'
        );
    }
}
