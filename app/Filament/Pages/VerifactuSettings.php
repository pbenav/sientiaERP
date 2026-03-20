<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class VerifactuSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.verifactu-settings';
    
    protected static ?string $navigationLabel = 'Veri*Factu';
    
    protected static ?string $title = 'Certificación Veri*Factu';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 110;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'verifactu_nif_emisor' => Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000')),
            'verifactu_nombre_emisor' => Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor', 'Sientia S.L.')),
            'verifactu_mode' => Setting::get('verifactu_mode', 'test'),
            'verifactu_cert_path' => Setting::get('verifactu_cert_path'),
            'verifactu_cert_password' => Setting::get('verifactu_cert_password'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identidad Fiscal')
                    ->description('Datos requeridos por la AEAT para el envío de facturas certificadas.')
                    ->schema([
                        TextInput::make('verifactu_nif_emisor')
                            ->label('NIF del Emisor')
                            ->required()
                            ->placeholder('Ej: B12345678'),
                        TextInput::make('verifactu_nombre_emisor')
                            ->label('Nombre / Razón Social')
                            ->required()
                            ->placeholder('Ej: Mi Empresa S.L.'),
                    ])->columns(2),

                Section::make('Conexión y Certificación')
                    ->description('Configuración técnica del canal seguro con la Agencia Tributaria.')
                    ->schema([
                        Select::make('verifactu_mode')
                            ->label('Entorno de Trabajo')
                            ->options([
                                'test' => 'PRUEBAS (Sandbox AEAT)',
                                'production' => 'PRODUCCIÓN (Real)',
                            ])
                            ->required()
                            ->native(false),
                        FileUpload::make('verifactu_cert_path')
                            ->label('Certificado Digital (.p12 / .pfx)')
                            ->directory('certs')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/x-pkcs12']),
                        TextInput::make('verifactu_cert_password')
                            ->label('Contraseña del Certificado')
                            ->password()
                            ->revealable(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // Handle file upload if it's an array (Filament returns an array for multiple or single file upload depending on config)
            $valToSave = is_array($value) ? (count($value) > 0 ? reset($value) : null) : $value;
            Setting::set($key, $valToSave);
        }

        Notification::make()
            ->title('Configuración Veri*Factu guardada')
            ->success()
            ->send();
    }
}
