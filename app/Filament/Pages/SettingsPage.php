<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
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
    
    protected static ?string $navigationGroup = 'Sistema';
    
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

                Section::make('Punto de Venta (POS)')
                    ->description('Configuración del sistema de punto de venta')
                    ->schema([
                        \Filament\Forms\Components\Select::make('pos_default_tercero_id')
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
                'pos_default_tercero_id' => 'Cliente por Defecto POS',
            ];
            
            $groups = [
                'pdf_logo_type' => 'PDF',
                'pdf_logo_text' => 'PDF',
                'pdf_logo_image' => 'PDF',
                'pdf_header_html' => 'PDF',
                'pdf_footer_text' => 'PDF',
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
