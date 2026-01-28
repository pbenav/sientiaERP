<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings-page';
    
    protected static ?string $navigationLabel = 'Ajustes Generales';
    
    protected static ?string $title = 'Configuración del Sistema';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'pdf_logo_type' => Setting::get('pdf_logo_type', 'text'),
            'pdf_logo_text' => Setting::get('pdf_logo_text', 'sienteERP System'),
            'pdf_logo_image' => Setting::get('pdf_logo_image'),
            'pdf_header_html' => Setting::get('pdf_header_html', '<strong>Sientia SL</strong><br>NIF: B12345678<br>Calle Falsa 123, 28001 Madrid'),
            'pdf_footer_text' => Setting::get('pdf_footer_text', 'sienteERP System'),
            'currency_symbol' => Setting::get('currency_symbol', '€'),
            'currency_position' => Setting::get('currency_position', 'suffix'),
            'decimal_separator' => Setting::get('decimal_separator', ','),
            'thousands_separator' => Setting::get('thousands_separator', '.'),
            'locale' => Setting::get('locale', 'es'),
            'timezone' => Setting::get('timezone', 'Europe/Madrid'),
            'pos_default_tercero_id' => Setting::get('pos_default_tercero_id'),
            'default_commercial_margin' => Setting::get('default_commercial_margin', 30),
            'default_supplier_id' => Setting::get('default_supplier_id'),
            'default_tax_rate' => Setting::get('default_tax_rate', 21),
            'display_uppercase' => Setting::get('display_uppercase', 'false'),
            'barcode_type' => Setting::get('barcode_type', 'code128'),
            // AI Settings
            'ai_provider' => Setting::get('ai_provider', 'gemini'),
            'ai_backup_provider' => Setting::get('ai_backup_provider', 'none'),
            'google_location' => Setting::get('google_location', 'eu'),
            'google_project_id' => Setting::get('google_project_id'),
            'google_processor_id' => Setting::get('google_processor_id'),
            'google_application_credentials' => Setting::get('google_application_credentials'),
            'ai_gemini_api_key' => Setting::get('ai_gemini_api_key', config('services.google.ai_api_key')),
            'ai_openai_api_key' => Setting::get('ai_openai_api_key'),
            'tesseract_path' => Setting::get('tesseract_path', '/usr/bin/tesseract'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Logo de la Aplicación (PDFs)')
                    ->description('Personaliza cómo aparece tu marca en los documentos PDF generados')
                    ->schema([
                        Radio::make('pdf_logo_type')
                            ->label('Tipo de Logo')
                            ->options([
                                'text' => 'Texto personalizado',
                                'image' => 'Imagen (Logo)',
                            ])
                            ->default('text')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('pdf_logo_text')
                            ->label('Texto del Logo')
                            ->placeholder('sienteERP System')
                            ->default('sienteERP System')
                            ->maxLength(100)
                            ->visible(fn($get) => $get('pdf_logo_type') === 'text')
                            ->columnSpanFull(),

                        FileUpload::make('pdf_logo_image')
                            ->label('Imagen del Logo')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:3',
                            ])
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('150')
                            ->directory('logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Recomendado: 800x150px (proporción 16:3). Máximo 2MB.')
                            ->visible(fn($get) => $get('pdf_logo_type') === 'image')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cabecera y Pie de Página (PDFs)')
                    ->description('Personaliza la información que aparece en los documentos')
                    ->schema([
                        Textarea::make('pdf_header_html')
                            ->label('Información de Cabecera')
                            ->helperText('HTML permitido. Aparece debajo del logo.')
                            ->rows(3)
                            ->placeholder('<strong>Nombre de tu empresa</strong><br>NIF: ...<br>Dirección...')
                            ->columnSpanFull(),

                        TextInput::make('pdf_footer_text')
                            ->label('Texto del Pie de Página')
                            ->placeholder('sienteERP System')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),

                Section::make('Moneda y Formato de Números')
                    ->description('Cómo se muestran los importes en toda la aplicación')
                    ->schema([
                        TextInput::make('currency_symbol')
                            ->label('Símbolo de Moneda')
                            ->default('€')
                            ->maxLength(5)
                            ->placeholder('€')
                            ->columnSpan(1),

                        Select::make('currency_position')
                            ->label('Posición del Símbolo')
                            ->options([
                                'suffix' => 'Después del importe (100,00 €)',
                                'prefix' => 'Antes del importe (€ 100,00)',
                            ])
                            ->default('suffix')
                            ->columnSpan(1),

                        Select::make('decimal_separator')
                            ->label('Separador Decimal')
                            ->options([
                                ',' => 'Coma (100,50)',
                                '.' => 'Punto (100.50)',
                            ])
                            ->default(',')
                            ->columnSpan(1),

                        Select::make('thousands_separator')
                            ->label('Separador de Miles')
                            ->options([
                                '.' => 'Punto (1.000,00)',
                                ',' => 'Coma (1,000.00)',
                                ' ' => 'Espacio (1 000,00)',
                                '' => 'Ninguno (1000,00)',
                            ])
                            ->default('.')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Idioma y Localización')
                    ->description('Configuración regional de la aplicación')
                    ->schema([
                        Select::make('locale')
                            ->label('Idioma')
                            ->options([
                                'es' => 'Español',
                                'en' => 'English',
                                'ca' => 'Català',
                            ])
                            ->default('es')
                            ->helperText('Idioma de la interfaz de usuario')
                            ->columnSpan(1),

                        Select::make('timezone')
                            ->label('Zona Horaria')
                            ->options([
                                'Europe/Madrid' => 'Madrid (UTC+1/+2)',
                                'Europe/London' => 'Londres (UTC+0/+1)',
                                'America/New_York' => 'Nueva York (UTC-5/-4)',
                                'America/Los_Angeles' => 'Los Ángeles (UTC-8/-7)',
                            ])
                            ->default('Europe/Madrid')
                            ->searchable()
                            ->helperText('Zona horaria para fechas y horas')
                            ->columnSpan(1),
                        
                        Select::make('display_uppercase')
                            ->label('Mostrar Todo en Mayúsculas')
                            ->options([
                                'false' => 'No - Capitalización normal',
                                'true' => 'Sí - Todo en MAYÚSCULAS',
                            ])
                            ->default('false')
                            ->helperText('Si está activado, todos los textos (nombres, descripciones, etc.) se mostrarán en MAYÚSCULAS')
                            ->columnSpanFull(),
                        
                        Select::make('barcode_type')
                            ->label('Tipo de Código de Barras (Etiquetas)')
                            ->options([
                                'code128' => 'Code 128',
                                'code39' => 'Code 39',
                                'ean13' => 'EAN-13',
                                'qr' => 'QR Code',
                            ])
                            ->default('code128')
                            ->helperText('Formato de código de barras para imprimir en etiquetas de productos')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Punto de Venta (POS)')
                    ->description('Configuración del sistema de punto de venta')
                    ->schema([
                        Select::make('pos_default_tercero_id')
                            ->label('Cliente por Defecto')
                            ->options(fn() => \App\Models\Tercero::clientes()->pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Cliente que se selecciona automáticamente al crear un nuevo ticket')
                            ->columnSpanFull(),
                        
                        Select::make('default_supplier_id')
                            ->label('Proveedor por Defecto (OCR)')
                            ->options(fn() => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Proveedor que se selecciona automáticamente al importar un albarán vía OCR')
                            ->columnSpanFull(),
                    ]),

                Section::make('Importación OCR')
                    ->description('Configuración para la importación de documentos mediante OCR')
                    ->schema([
                        TextInput::make('default_commercial_margin')
                            ->label('Margen Comercial por Defecto (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(30)
                            ->suffix('%')
                            ->helperText('Margen que se aplicará automáticamente a los productos importados vía OCR para calcular el PVP')
                            ->columnSpan(1),
                        
                        TextInput::make('default_tax_rate')
                            ->label('IVA por Defecto (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(21)
                            ->suffix('%')
                            ->helperText('Tasa de IVA que se aplicará por defecto en los cálculos de precio de venta')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Orden y Tiempos')
                    ->schema([
                         // ... existing logic if any, or just close previous section
                    ])->hidden(), // Placeholder if needed or just remove closing bracket issue

                Section::make('Automatización e Inteligencia Artificial')
                    ->description('Configura los proveedores de IA para procesar documentos automáticamente.')
                    ->schema([
                        Select::make('ai_provider')
                            ->label('Proveedor IA Principal')
                            ->options([
                                'google_doc_ai' => 'Google Cloud Document AI (Invoice Processor)',
                                'gemini' => 'Google Gemini (GenAI)',
                                'openai' => 'OpenAI (ChatGPT)',
                            ])
                            ->default('gemini')
                            ->required()
                            ->columnSpanFull()
                            ->live(),

                        Select::make('ai_backup_provider')
                            ->label('Proveedor Backup (Fallo Principal)')
                            ->options([
                                'none' => 'Ninguno',
                                'tesseract' => 'Tesseract OCR (Local)',
                            ])
                            ->default('none')
                            ->required()
                            ->columnSpanFull(),

                        // GROUP: Google Cloud Doc AI
                        Section::make('Configuración Google Cloud')
                            ->schema([
                                Select::make('google_location')
                                    ->label('Ubicación')
                                    ->options(['us' => 'US (Estados Unidos)', 'eu' => 'EU (Unión Europea)'])
                                    ->default('eu')
                                    ->required(),
                                
                                TextInput::make('google_project_id')
                                    ->label('Project ID')
                                    ->required(),

                                TextInput::make('google_processor_id')
                                    ->label('Processor ID (Invoice)')
                                    ->required(),

                                Textarea::make('google_application_credentials')
                                    ->label('Service Account JSON')
                                    ->rows(5)
                                    ->helperText('Pega aquí el contenido completo del archivo .json descargado de Google Cloud IAM.')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn($get) => $get('ai_provider') === 'google_doc_ai')
                            ->columns(2),

                        // GROUP: Gemini
                        Section::make('Configuración Gemini')
                            ->schema([
                                TextInput::make('ai_gemini_api_key')
                                    ->label('Gemini API Key')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn($get) => $get('ai_provider') === 'gemini'),

                        // GROUP: OpenAI
                        Section::make('Configuración OpenAI')
                            ->schema([
                                TextInput::make('ai_openai_api_key')
                                    ->label('OpenAI API Key')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn($get) => $get('ai_provider') === 'openai'),

                        // GROUP: Tesseract
                        Section::make('Configuración Tesseract')
                            ->schema([
                                TextInput::make('tesseract_path')
                                    ->label('Ruta al Binario')
                                    ->default('/usr/bin/tesseract')
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn($get) => $get('ai_backup_provider') === 'tesseract'),

                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $labels = [
                'pdf_logo_type' => 'Tipo de Logo PDF',
                'pdf_logo_text' => 'Texto del Logo',
                'pdf_logo_image' => 'Imagen del Logo',
                'pdf_header_html' => 'HTML de Cabecera PDF',
                'pdf_footer_text' => 'Texto de Pie de Página PDF',
                'currency_symbol' => 'Símbolo de Moneda',
                'currency_position' => 'Posición del Símbolo',
                'decimal_separator' => 'Separador Decimal',
                'thousands_separator' => 'Separador de Miles',
                'locale' => 'Idioma',
                'timezone' => 'Zona Horaria',
                'pos_default_tercero_id' => 'Cliente por Defecto POS',
                'default_commercial_margin' => 'Margen Comercial por Defecto',
            ];
            
            $groups = [
                'pdf_logo_type' => 'PDF',
                'pdf_logo_text' => 'PDF',
                'pdf_logo_image' => 'PDF',
                'pdf_header_html' => 'PDF',
                'pdf_footer_text' => 'PDF',
                'currency_symbol' => 'Moneda',
                'currency_position' => 'Moneda',
                'decimal_separator' => 'Formato',
                'thousands_separator' => 'Formato',
                'locale' => 'Localización',
                'timezone' => 'Localización',
                'pos_default_tercero_id' => 'POS',
                'default_supplier_id' => 'POS',
                'default_commercial_margin' => 'OCR',
                'default_tax_rate' => 'OCR',
                'display_uppercase' => 'Localización',
                'barcode_type' => 'Localización',
                // AI Settings
                'ai_provider' => 'IA',
                'ai_backup_provider' => 'IA',
                'google_location' => 'IA',
                'google_project_id' => 'IA',
                'google_processor_id' => 'IA',
                'google_application_credentials' => 'IA',
                'ai_gemini_api_key' => 'IA',
                'ai_openai_api_key' => 'IA',
                'tesseract_path' => 'IA',
            ];

            Setting::set($key, $value, $labels[$key] ?? $key, $groups[$key] ?? 'General');
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
