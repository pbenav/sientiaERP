<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class AiSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.ai-settings';
    
    protected static ?string $navigationLabel = 'AI / Automatización';
    
    protected static ?string $title = 'IA & Automatización';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 120;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ai_provider' => Setting::get('ai_provider', 'gemini'),
            'ai_backup_provider' => Setting::get('ai_backup_provider', 'none'),
            'google_location' => Setting::get('google_location', 'eu'),
            'google_project_id' => Setting::get('google_project_id'),
            'google_processor_id' => Setting::get('google_processor_id'),
            'google_application_credentials' => Setting::get('google_application_credentials'),
            'ai_gemini_api_key' => Setting::get('ai_gemini_api_key'),
            'ai_gemini_model' => Setting::get('ai_gemini_model', 'gemini-1.5-flash'),
            'ai_openai_api_key' => Setting::get('ai_openai_api_key'),
            'tesseract_path' => Setting::get('tesseract_path', '/usr/bin/tesseract'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Proveedor de Inteligencia')
                    ->description('Selecciona el motor de IA que impulsará las predicciones y OCR.')
                    ->schema([
                        Select::make('ai_provider')
                            ->label('Proveedor Principal')
                            ->options([
                                'gemini' => 'Google Gemini (Recomendado)',
                                'openai' => 'OpenAI (GPT-4)',
                                'google_doc_ai' => 'Google Cloud Doc AI (OCR Enterprise)',
                                'local_tesseract' => 'Local (Tesseract OCR)',
                            ])
                            ->live()
                            ->required(),
                        Select::make('ai_backup_provider')
                            ->label('Proveedor de Respaldo')
                            ->options([
                                'none' => 'Ninguno',
                                'gemini' => 'Gemini',
                                'openai' => 'OpenAI',
                            ]),
                    ])->columns(2),

                Section::make('Credenciales y Modelos')
                    ->description('Configura las llaves de API y modelos específicos.')
                    ->schema([
                        TextInput::make('ai_gemini_api_key')
                            ->label('API Key (Google Gemini)')
                            ->password()
                            ->revealable()
                            ->columnSpanFull()
                            ->visible(fn($get) => in_array($get('ai_provider'), ['gemini']) || in_array($get('ai_backup_provider'), ['gemini'])),
                        
                        Select::make('ai_gemini_model')
                            ->label('Modelo de Gemini')
                            ->options([
                                'gemini-1.5-flash' => '1.5 Flash (Rápido/Económico)',
                                'gemini-1.5-pro' => '1.5 Pro (Complejo/Preciso)',
                                'gemini-1.0-pro' => '1.0 Pro (Legado)',
                            ])
                            ->visible(fn($get) => in_array($get('ai_provider'), ['gemini']) || in_array($get('ai_backup_provider'), ['gemini'])),

                        TextInput::make('ai_openai_api_key')
                            ->label('API Key (OpenAI)')
                            ->password()
                            ->revealable()
                            ->columnSpanFull()
                            ->visible(fn($get) => in_array($get('ai_provider'), ['openai']) || in_array($get('ai_backup_provider'), ['openai'])),

                        Section::make('Google Cloud (Enterprise OCR)')
                            ->visible(fn($get) => $get('ai_provider') === 'google_doc_ai')
                            ->schema([
                                TextInput::make('google_project_id')->label('Project ID'),
                                TextInput::make('google_processor_id')->label('Processor ID'),
                                Select::make('google_location')->label('Ubicación')->options(['eu' => 'Europa', 'us' => 'EEUU']),
                                Textarea::make('google_application_credentials')
                                    ->label('JSON de Credenciales')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ])->columns(3),
                        
                        TextInput::make('tesseract_path')
                            ->label('Ruta de Tesseract OCR')
                            ->placeholder('/usr/bin/tesseract')
                            ->visible(fn($get) => $get('ai_provider') === 'local_tesseract'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Configuración AI Guardada')
            ->success()
            ->send();
    }
}
