<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings-page';
    
    protected static ?string $navigationLabel = 'Configuración';
    
    protected static ?string $title = 'Configuración del Sistema';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // General
            'pdf_logo_type' => Setting::get('pdf_logo_type', 'text'),
            'pdf_logo_text' => Setting::get('pdf_logo_text', 'sienteERP System'),
            'pdf_logo_image' => Setting::get('pdf_logo_image'),
            'pdf_header_html' => Setting::get('pdf_header_html', '<strong>Sientia SL</strong><br>NIF: B12345678'),
            'pdf_footer_text' => Setting::get('pdf_footer_text', 'sienteERP System'),
            'currency_symbol' => Setting::get('currency_symbol', '€'),
            'currency_position' => Setting::get('currency_position', 'suffix'),
            'decimal_separator' => str_replace(['comma', 'dot'], [',', '.'], Setting::get('decimal_separator', ',')),
            'thousands_separator' => str_replace(['comma', 'dot'], [',', '.'], Setting::get('thousands_separator', '.')),
            'locale' => Setting::get('locale', 'es'),
            'timezone' => Setting::get('timezone', 'Europe/Madrid'),
            'intermediate_precision' => Setting::get('intermediate_precision', 3),
            'final_precision' => Setting::get('final_precision', 2),
            
            // Valores por Defecto
            'default_tercero_id' => Setting::get('default_tercero_id'),
            'default_supplier_id' => Setting::get('default_supplier_id'),
            'default_forma_pago_id' => Setting::get('default_forma_pago_id'),
            'default_tax_rate' => Setting::get('default_tax_rate', 21),
            
            // TPV & Precios
            'pos_default_tercero_id' => Setting::get('pos_default_tercero_id'),
            'pos_quick_skus' => Setting::get('pos_quick_skus', 'BOLSA,VARIO,GENERICO'),
            'profit_calculation_method' => Setting::get('profit_calculation_method', 'from_purchase'),
            'default_profit_percentage' => Setting::get('default_profit_percentage', 60),
            'default_tax_rate' => Setting::get('default_tax_rate', 21),
            'display_uppercase' => Setting::get('display_uppercase', 'false'),
            'barcode_type' => Setting::get('barcode_type', 'code128'),
            'default_commercial_margin' => Setting::get('default_commercial_margin', 30),
            'presupuesto_validez_dias' => Setting::get('presupuesto_validez_dias', 15),
            'show_price_on_label' => Setting::get('show_price_on_label', 'true') === 'true',
            'default_supplier_id' => Setting::get('default_supplier_id'),
            'google_location' => Setting::get('google_location', 'eu'),
            'google_project_id' => Setting::get('google_project_id'),
            'google_processor_id' => Setting::get('google_processor_id'),
            'google_application_credentials' => Setting::get('google_application_credentials'),

            // AI
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

            // Verifactu
            'verifactu_nif_emisor' => Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor')),
            'verifactu_nombre_emisor' => Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor')),
            'verifactu_mode' => Setting::get('verifactu_mode', 'test'),
            'verifactu_cert_path' => Setting::get('verifactu_cert_path'),
            'verifactu_cert_password' => Setting::get('verifactu_cert_password'),
            'verifactu_active' => (bool) Setting::get('verifactu_active', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('SettingsTabs')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-presentation-chart-bar')
                            ->schema([
                                Section::make('Identidad Visual')
                                    ->schema([
                                        Radio::make('pdf_logo_type')
                                            ->label('Tipo de Logo')
                                            ->options(['text' => 'Texto', 'image' => 'Imagen'])
                                            ->live()
                                            ->columnSpanFull(),
                                        TextInput::make('pdf_logo_text')
                                            ->label('Texto del Logo')
                                            ->visible(fn($get) => $get('pdf_logo_type') === 'text')
                                            ->columnSpanFull(),
                                        FileUpload::make('pdf_logo_image')
                                            ->label('Imagen Logo')
                                            ->image()
                                            ->directory('logos')
                                            ->visible(fn($get) => $get('pdf_logo_type') === 'image')
                                            ->columnSpanFull(),
                                        Textarea::make('pdf_header_html')->label('Cabecera Fiscal (HTML)')->rows(2)->columnSpanFull(),
                                        TextInput::make('pdf_footer_text')->label('Pie de Página')->columnSpanFull(),
                                    ]),
                                Section::make('Localización y Formato')
                                    ->schema([
                                        Select::make('locale')->label('Idioma')->options(['es' => 'Español', 'en' => 'English']),
                                        Select::make('timezone')->label('Zona Horaria')->options(['Europe/Madrid' => 'Madrid']),
                                        TextInput::make('currency_symbol')->label('Símbolo €'),
                                        Select::make('currency_position')->label('Posición')->options(['suffix' => 'Sufijo', 'prefix' => 'Prefijo']),
                                        Select::make('decimal_separator')
                                            ->label('Separador Decimal')
                                            ->options([
                                                ',' => 'Coma (,)',
                                                '.' => 'Punto (.)',
                                            ])
                                            ->required(),
                                        Select::make('thousands_separator')
                                            ->label('Separador Miles')
                                            ->options([
                                                '.' => 'Punto (.)',
                                                ',' => 'Coma (,)',
                                                ' ' => 'Espacio ( )',
                                                '' => 'Ninguno',
                                            ])
                                            ->required(),
                                        Select::make('display_uppercase')
                                            ->label('Forzar Mayúsculas Globales')
                                            ->options([
                                                'true' => 'SÍ (Todo en Mayúsculas)',
                                                'false' => 'NO (Normal / Tal cual)',
                                            ])
                                            ->default('false'),
                                    ])->columns(2),
                                Section::make('Finanzas y Precisión')
                                    ->description('Controla el redondeo de los cálculos matemáticos.')
                                    ->schema([
                                        TextInput::make('intermediate_precision')
                                            ->label('Decimales Intermedios')
                                            ->helperText('Usados en cálculos de líneas antes de sumar totales.')
                                            ->numeric()->default(3),
                                        TextInput::make('final_precision')
                                            ->label('Decimales Finales (PVP)')
                                            ->helperText('Usados para el total final del documento.')
                                            ->numeric()->default(2),
                                    ])->columns(2),
                                Section::make('Valores por Defecto en Compras y Ventas')
                                    ->description('Parámetros automáticos para nuevas facturas y compras.')
                                    ->schema([
                                        Select::make('default_tercero_id')
                                            ->label('Cliente Preferente')
                                            ->options(fn() => \App\Models\Tercero::clientes()->pluck('nombre_comercial', 'id'))
                                            ->searchable(),
                                        Select::make('default_supplier_id')
                                            ->label('Proveedor Preferente')
                                            ->options(fn() => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                                            ->searchable(),
                                        Select::make('default_forma_pago_id')
                                            ->label('Forma de Pago')
                                            ->options(fn() => \App\Models\FormaPago::activas()->pluck('nombre', 'id'))
                                            ->searchable(),
                                        TextInput::make('default_tax_rate')
                                            ->label('IVA Predeterminado (%)')
                                            ->numeric()
                                            ->suffix('%'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('TPV & Precios')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Section::make('Operativa TPV')
                                    ->schema([
                                        Select::make('pos_default_tercero_id')
                                            ->label('Cliente por Defecto')
                                            ->options(fn() => \App\Models\Tercero::pluck('nombre_comercial', 'id'))
                                            ->searchable(),
                                        TextInput::make('pos_quick_skus')
                                            ->label('SKUs Acceso Rápido')
                                            ->placeholder('BOLSA,VARIO'),
                                        Select::make('barcode_type')
                                            ->label('Tipo de Códigos')
                                            ->options([
                                                'code128' => 'Code 128 (Estándar)',
                                                'code39' => 'Code 39 (Industrial)',
                                                'ean13' => 'EAN-13 (Comercial)',
                                                'ean8' => 'EAN-8 (Compacto)',
                                                'qrcode' => 'QR Code (Digital)',
                                            ]),
                                    ])->columns(2),
                                Section::make('Cálculo de Beneficios y Documentos')
                                    ->schema([
                                        Select::make('profit_calculation_method')
                                            ->label('Método de Margen')
                                            ->options([
                                                'from_purchase' => 'Sobre Compra',
                                                'from_sale' => 'Sobre Venta',
                                            ]),
                                        TextInput::make('default_profit_percentage')->label('Margen Defecto (%)')->numeric()->suffix('%'),
                                        TextInput::make('default_tax_rate')->label('IVA Defecto (%)')->numeric()->suffix('%'),
                                        TextInput::make('default_commercial_margin')->label('Margen OCR (%)')->numeric()->suffix('%'),
                                        TextInput::make('presupuesto_validez_dias')->label('Validez Presupuesto')->numeric()->suffix('días'),
                                        Toggle::make('show_price_on_label')->label('Precio en Etiquetas')->default(true),
                                    ])->columns(3),
                            ]),

                        Tabs\Tab::make('Automatización / IA')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Section::make('Proveedores')
                                    ->schema([
                                        Select::make('ai_provider')
                                            ->label('Proveedor Principal')
                                            ->options([
                                                'gemini' => 'Google Gemini (Recomendado)', 
                                                'openai' => 'OpenAI (GPT-4)', 
                                                'google_doc_ai' => 'Google Cloud Doc AI',
                                                'local_tesseract' => 'Local (Tesseract OCR)'
                                            ])
                                            ->live()
                                            ->required(),
                                        Select::make('ai_backup_provider')
                                            ->label('Proveedor de Respaldo')
                                            ->options([
                                                'none' => 'Ninguno',
                                                'gemini' => 'Gemini',
                                                'openai' => 'OpenAI',
                                                'local_tesseract' => 'Tesseract OCR'
                                            ]),
                                        Select::make('default_supplier_id')
                                            ->label('Proveedor por Defecto (OCR)')
                                            ->options(fn() => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                                            ->searchable()
                                            ->helperText('Utilizado cuando el OCR no identifica al emisor.'),
                                    ])->columns(3),

                                Section::make('Credenciales y Modelos')
                                    ->schema([
                                        // Gemini
                                        TextInput::make('ai_gemini_api_key')
                                            ->label('Gemini API Key')
                                            ->password()->revealable()
                                            ->visible(fn($get) => $get('ai_provider') === 'gemini' || $get('ai_backup_provider') === 'gemini')
                                            ->columnSpanFull(),
                                        Select::make('ai_gemini_model')
                                            ->label('Modelo Gemini')
                                            ->options(['gemini-1.5-flash' => '1.5 Flash', 'gemini-1.5-pro' => '1.5 Pro'])
                                            ->visible(fn($get) => $get('ai_provider') === 'gemini' || $get('ai_backup_provider') === 'gemini'),

                                        // OpenAI
                                        TextInput::make('ai_openai_api_key')
                                            ->label('OpenAI API Key')
                                            ->password()->revealable()
                                            ->visible(fn($get) => $get('ai_provider') === 'openai' || $get('ai_backup_provider') === 'openai')
                                            ->columnSpanFull(),

                                        // Google Doc AI
                                        Group::make([
                                            Select::make('google_location')->label('Ubicación')->options(['eu' => 'Europa (EU)', 'us' => 'Estados Unidos (US)']),
                                            TextInput::make('google_project_id')->label('Google Project ID'),
                                            TextInput::make('google_processor_id')->label('Processor ID'),
                                            Textarea::make('google_application_credentials')
                                                ->label('Service Account JSON')
                                                ->rows(4)
                                                ->placeholder('Paste your JSON key here...')
                                                ->columnSpanFull(),
                                        ])->columns(3)->visible(fn($get) => $get('ai_provider') === 'google_doc_ai'),

                                        // Tesseract
                                        TextInput::make('tesseract_path')
                                            ->label('Ruta Tesseract Binary')
                                            ->placeholder('/usr/bin/tesseract')
                                            ->visible(fn($get) => $get('ai_provider') === 'local_tesseract' || $get('ai_backup_provider') === 'local_tesseract')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Veri*Factu (Anti-Fraude)')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Legal & Envío')
                                    ->description('Configura la identidad digital para AEAT')
                                    ->schema([
                                        TextInput::make('verifactu_nif_emisor')->label('NIF Emisor')->required(),
                                        TextInput::make('verifactu_nombre_emisor')->label('Nombre Emisor')->required(),
                                        Select::make('verifactu_mode')->label('Modo')->options(['test' => 'PRUEBAS', 'production' => 'PRODUCCIÓN']),
                                        Toggle::make('verifactu_active')
                                            ->label('Activar Veri*Factu')
                                            ->helperText('Habilita el encadenamiento de facturas y envío a la AEAT.')
                                            ->default(false),
                                        FileUpload::make('verifactu_cert_path')->label('Certificado (.p12)')->directory('certs')->visibility('private'),
                                        TextInput::make('verifactu_cert_password')->label('Pass Certificado')->password()->revealable(),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull()
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $oldMethod = Setting::get('profit_calculation_method', 'from_purchase');
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $valToSave = is_array($value) ? (count($value) > 0 ? reset($value) : null) : $value;
            Setting::set($key, $valToSave);
        }
        
        if ($oldMethod !== ($data['profit_calculation_method'] ?? $oldMethod)) {
            \App\Models\Product::all()->each(function($product) {
                $product->recalculateProfitMargin();
                $product->save();
            });
            Notification::make()->title('Márgenes actualizados')->info()->send();
        }

        Notification::make()->title('Configuración guardada satisfactoriamente')->success()->send();
        
        $this->redirect(static::getUrl());
    }
}
