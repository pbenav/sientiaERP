<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Tercero;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class PosSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'filament.pages.pos-settings';
    
    protected static ?string $navigationLabel = 'TPV / Punto de Venta';
    
    protected static ?string $title = 'Configuración del Terminal de Venta';
    
    protected static ?string $navigationGroup = 'Configuración';
    
    protected static ?int $navigationSort = 130;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Parámetros de Venta')
                    ->description('Configura el comportamiento del mostrador y tickets.')
                    ->schema([
                        Select::make('pos_default_tercero_id')
                            ->label('Cliente de Mostrador (Por Defecto)')
                            ->options(Tercero::pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('pos_quick_skus')
                            ->label('Artículos de Acceso Rápido')
                            ->placeholder('Ej: BOLSA,VARIO,TARIFA1')
                            ->helperText('Separa los SKUs por comas.')
                            ->columnSpanFull(),
                        Toggle::make('display_uppercase')
                            ->label('Mostrar Textos en Mayúsculas')
                            ->onIcon('heroicon-o-eye')
                            ->offIcon('heroicon-o-eye-slash'),
                        Select::make('barcode_type')
                            ->label('Tipo de Código de Barras')
                            ->options([
                                'code128' => 'Code 128 (Moderno)',
                                'ean13' => 'EAN-13 (Comercial)',
                                'qrcode' => 'QR Code (Digital)',
                            ]),
                    ])->columns(2),

                Section::make('Márgenes e Impuestos')
                    ->description('Valores predeterminados para nuevos productos y presupuestos.')
                    ->schema([
                        Select::make('profit_calculation_method')
                            ->label('Cálculo de Margen')
                            ->options([
                                'from_purchase' => 'Markup (Sobre coste de compra)',
                                'from_sale' => 'Margen Neto (Sobre precio de venta)',
                            ])
                            ->required(),
                        TextInput::make('default_profit_percentage')
                            ->label('Margen por Defecto (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                        TextInput::make('default_tax_rate')
                            ->label('Tipo de IVA Predeterminado (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                        TextInput::make('default_commercial_margin')
                            ->label('Margen OCR Predeterminado (%)')
                            ->numeric()
                            ->suffix('%'),
                        TextInput::make('presupuesto_validez_dias')
                            ->label('Validez de Presupuestos')
                            ->numeric()
                            ->suffix('días'),
                        Toggle::make('show_price_on_label')
                            ->label('Mostrar Precio en Etiquetas')
                            ->default(true),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $oldMethod = Setting::get('profit_calculation_method', 'from_purchase');
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        if ($oldMethod !== ($data['profit_calculation_method'] ?? $oldMethod)) {
            \App\Models\Product::all()->each(function($product) {
                $product->recalculateProfitMargin();
                $product->save();
            });
            Notification::make()->title('Márgenes recalculados en productos')->info()->send();
        }

        Notification::make()
            ->title('Configuración TPV Guardada')
            ->success()
            ->send();
            
        $this->redirect(static::getUrl());
    }
}
