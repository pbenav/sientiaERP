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
    
    protected static ?string $navigationLabel = 'Configuración';
    
    protected static ?string $title = 'Configuración del Sistema';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'pdf_logo_type' => Setting::get('pdf_logo_type', 'text'),
            'pdf_logo_text' => Setting::get('pdf_logo_text', 'nexERP System'),
            'pdf_logo_image' => Setting::get('pdf_logo_image'),
            'pdf_header_html' => Setting::get('pdf_header_html', '<strong>Sientia SL</strong><br>NIF: B12345678<br>Calle Falsa 123, 28001 Madrid'),
            'pdf_footer_text' => Setting::get('pdf_footer_text', 'nexERP System'),
            'currency_symbol' => Setting::get('currency_symbol', '€'),
            'currency_position' => Setting::get('currency_position', 'suffix'),
            'decimal_separator' => Setting::get('decimal_separator', ','),
            'thousands_separator' => Setting::get('thousands_separator', '.'),
            'locale' => Setting::get('locale', 'es'),
            'timezone' => Setting::get('timezone', 'Europe/Madrid'),
            'pos_default_tercero_id' => Setting::get('pos_default_tercero_id'),
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
                            ->placeholder('nexERP System')
                            ->default('nexERP System')
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
                            ->placeholder('nexERP System')
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
            ];

            Setting::set($key, $value, $labels[$key] ?? $key, $groups[$key] ?? 'General');
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
