<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
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
            'decimal_separator' => Setting::get('decimal_separator', ','),
            'thousands_separator' => Setting::get('thousands_separator', '.'),
            'locale' => Setting::get('locale', 'es'),
            'timezone' => Setting::get('timezone', 'Europe/Madrid'),
            
            // TPV
            'pos_default_tercero_id' => Setting::get('pos_default_tercero_id'),
            'pos_quick_skus' => Setting::get('pos_quick_skus', 'BOLSA,VARIO,GENERICO'),
            'profit_calculation_method' => Setting::get('profit_calculation_method', 'from_purchase'),
            'default_profit_percentage' => Setting::get('default_profit_percentage', 60),
            'default_tax_rate' => Setting::get('default_tax_rate', 21),
            'display_uppercase' => Setting::get('display_uppercase', 'false'),
            'barcode_type' => Setting::get('barcode_type', 'code128'),

            // AI
            'ai_provider' => Setting::get('ai_provider', 'gemini'),
            'ai_backup_provider' => Setting::get('ai_backup_provider', 'none'),
            'google_location' => Setting::get('google_location', 'eu'),
            'google_project_id' => Setting::get('google_project_id'),
            'google_processor_id' => Setting::get('google_processor_id'),
            'google_application_credentials' => Setting::get('google_application_credentials'),
            'ai_gemini_api_key' => Setting::get('ai_gemini_api_key'),
            'ai_openai_api_key' => Setting::get('ai_openai_api_key'),
            'tesseract_path' => Setting::get('tesseract_path', '/usr/bin/tesseract'),

            // Verifactu
            'verifactu_nif_emisor' => Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor')),
            'verifactu_nombre_emisor' => Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor')),
            'verifactu_mode' => Setting::get('verifactu_mode', 'test'),
            'verifactu_cert_path' => Setting::get('verifactu_cert_path'),
            'verifactu_cert_password' => Setting::get('verifactu_cert_password'),
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
                                        Textarea::make('pdf_header_html')->label('Cabecera (HTML)')->rows(2)->columnSpanFull(),
                                        TextInput::make('pdf_footer_text')->label('Pie de Página')->columnSpanFull(),
                                    ]),
                                Section::make('Localización y Formato')
                                    ->schema([
                                        Select::make('locale')->label('Idioma')->options(['es' => 'Español', 'en' => 'English']),
                                        Select::make('timezone')->label('Zona Horaria')->options(['Europe/Madrid' => 'Madrid']),
                                        TextInput::make('currency_symbol')->label('Símbolo €'),
                                        Select::make('currency_position')->label('Posición')->options(['suffix' => 'Sufijo', 'prefix' => 'Prefijo']),
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
                                    ])->columns(2),
                                Section::make('Cálculo de Beneficios')
                                    ->schema([
                                        Select::make('profit_calculation_method')
                                            ->label('Método de Margen')
                                            ->options([
                                                'from_purchase' => 'Sobre Compra',
                                                'from_sale' => 'Sobre Venta',
                                            ]),
                                        TextInput::make('default_profit_percentage')->label('Margen Defecto (%)')->numeric()->suffix('%'),
                                        TextInput::make('default_tax_rate')->label('IVA Defecto (%)')->numeric()->suffix('%'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Automatización / IA')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Select::make('ai_provider')
                                    ->label('Proveedor IA')
                                    ->options(['gemini' => 'Gemini', 'openai' => 'OpenAI', 'google_doc_ai' => 'Google Cloud Doc AI'])
                                    ->live(),
                                Section::make('Credenciales')
                                    ->schema([
                                        TextInput::make('ai_gemini_api_key')->label('Gemini Key')->password()->revealable()->visible(fn($get) => $get('ai_provider') === 'gemini'),
                                        TextInput::make('ai_openai_api_key')->label('OpenAI Key')->password()->revealable()->visible(fn($get) => $get('ai_provider') === 'openai'),
                                        Textarea::make('google_application_credentials')->label('Google JSON')->rows(4)->visible(fn($get) => $get('ai_provider') === 'google_doc_ai'),
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

        foreach ($data['SettingsTabs'] as $tabKey => $tabData) {
             // En Filament V3 con Tabs, el state puede venir anidado o plano dependiendo de si Tabs tiene statePath.
             // Aquí lo manejamos asumiendo que el statePath de Page es 'data'.
        }
        
        // Re-mapeamos datos planos si vinieron así
        $flatData = \Illuminate\Support\Arr::dot($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) continue; // Evitar pasar el array del tab si Filament lo anida
            Setting::set($key, $value);
        }
        
        // Si el tab anida los datos, los extraemos
        // En este caso, al no poner statePath en Tabs, vienen en el nivel raíz de $data.
        
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
