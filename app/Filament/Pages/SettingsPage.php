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
            'intermediate_precision' => Setting::get('intermediate_precision', 3),
            'final_precision' => Setting::get('final_precision', 2),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identidad de Empresa')
                    ->description('Configura los datos que aparecerán en tus facturas y documentos.')
                    ->schema([
                        Radio::make('pdf_logo_type')
                            ->label('Tipo de Logo')
                            ->options(['text' => 'Texto / Nombre', 'image' => 'Logotipo (Imagen)'])
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('pdf_logo_text')
                            ->label('Nombre de la Empresa')
                            ->visible(fn($get) => $get('pdf_logo_type') === 'text')
                            ->columnSpanFull(),
                        FileUpload::make('pdf_logo_image')
                            ->label('Logo para Documentos')
                            ->image()
                            ->directory('logos')
                            ->visible(fn($get) => $get('pdf_logo_type') === 'image')
                            ->columnSpanFull(),
                        Textarea::make('pdf_header_html')
                            ->label('Cabecera Fiscal (HTML)')
                            ->helperText('Aparece en la parte superior derecha de las facturas.')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('pdf_footer_text')
                            ->label('Texto Legal en Pie de Página')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Localización y Formato')
                    ->description('Ajustes de región, moneda y zona horaria.')
                    ->schema([
                        Select::make('locale')
                            ->label('Idioma del Sistema')
                            ->options(['es' => 'Español (Castellano)', 'en' => 'English (International)']),
                        Select::make('timezone')
                            ->label('Zona Horaria')
                            ->options(['Europe/Madrid' => 'Madrid (CET)', 'Atlantic/Canary' => 'Canarias (WET)']),
                        TextInput::make('currency_symbol')->label('Símbolo de Moneda'),
                        Select::make('currency_position')
                            ->label('Posición del Símbolo')
                            ->options(['suffix' => 'Símbolo al final (10,00€)', 'prefix' => 'Símbolo al inicio (€10.00)']),
                        TextInput::make('decimal_separator')->label('Separador Decimal'),
                        TextInput::make('thousands_separator')->label('Separador de Miles'),
                    ])->columns(2),

                Section::make('Finanzas y Precisión')
                    ->description('Controla el redondeo de los cálculos matemáticos.')
                    ->schema([
                        TextInput::make('intermediate_precision')
                            ->label('Decimales Intermedios')
                            ->helperText('Utilizados para cálculos de líneas antes de sumar totales.')
                            ->numeric()
                            ->default(3),
                        TextInput::make('final_precision')
                            ->label('Decimales Finales (PVP)')
                            ->helperText('Utilizados para el total final del documento.')
                            ->numeric()
                            ->default(2),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $valToSave = is_array($value) ? (count($value) > 0 ? reset($value) : null) : $value;
            Setting::set($key, $valToSave);
        }
        
        Notification::make()->title('Configuración general guardada')->success()->send();
        
        $this->redirect(static::getUrl());
    }
}
