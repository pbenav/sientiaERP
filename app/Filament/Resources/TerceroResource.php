<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerceroResource\Pages;
use App\Models\Tercero;
use App\Models\TipoTercero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TerceroResource extends Resource
{
    protected static ?string $model = Tercero::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Terceros';

    protected static ?string $modelLabel = 'Tercero';

    protected static ?string $pluralModelLabel = 'Terceros';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'Gestión';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos Básicos')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente'),
                        
                        Forms\Components\TextInput::make('nombre_comercial')
                            ->label('Nombre Comercial')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('nif_cif')
                            ->label('NIF/CIF')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        
                        Forms\Components\Select::make('tipos')
                            ->label('Tipos de Tercero')
                            ->multiple()
                            ->relationship('tipos', 'nombre')
                            ->preload()
                            ->required(),
                        
                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('persona_contacto')
                            ->label('Persona de Contacto')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),
                        
                        Forms\Components\TextInput::make('movil')
                            ->label('Móvil')
                            ->tel()
                            ->maxLength(20),
                        
                        Forms\Components\TextInput::make('web')
                            ->label('Web')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Dirección Fiscal')
                    ->schema([
                        Forms\Components\Textarea::make('direccion_fiscal')
                            ->label('Dirección')
                            ->rows(2)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('codigo_postal_fiscal')
                            ->label('Código Postal')
                            ->maxLength(10),
                        
                        Forms\Components\TextInput::make('poblacion_fiscal')
                            ->label('Población')
                            ->maxLength(100),
                        
                        Forms\Components\TextInput::make('provincia_fiscal')
                            ->label('Provincia')
                            ->maxLength(100),
                        
                        Forms\Components\TextInput::make('pais_fiscal')
                            ->label('País')
                            ->default('España')
                            ->maxLength(100),
                    ])->columns(2),

                Forms\Components\Section::make('Dirección de Envío')
                    ->schema([
                        Forms\Components\Toggle::make('direccion_envio_diferente')
                            ->label('Dirección de envío diferente a la fiscal')
                            ->live()
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('direccion_envio')
                            ->label('Dirección')
                            ->rows(2)
                            ->visible(fn (Forms\Get $get) => $get('direccion_envio_diferente'))
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('codigo_postal_envio')
                            ->label('Código Postal')
                            ->visible(fn (Forms\Get $get) => $get('direccion_envio_diferente'))
                            ->maxLength(10),
                        
                        Forms\Components\TextInput::make('poblacion_envio')
                            ->label('Población')
                            ->visible(fn (Forms\Get $get) => $get('direccion_envio_diferente'))
                            ->maxLength(100),
                        
                        Forms\Components\TextInput::make('provincia_envio')
                            ->label('Provincia')
                            ->visible(fn (Forms\Get $get) => $get('direccion_envio_diferente'))
                            ->maxLength(100),
                        
                        Forms\Components\TextInput::make('pais_envio')
                            ->label('País')
                            ->visible(fn (Forms\Get $get) => $get('direccion_envio_diferente'))
                            ->maxLength(100),
                    ])->columns(2),

                Forms\Components\Section::make('Datos Bancarios')
                    ->schema([
                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34),
                        
                        Forms\Components\TextInput::make('swift')
                            ->label('SWIFT/BIC')
                            ->maxLength(11),
                        
                        Forms\Components\TextInput::make('banco')
                            ->label('Banco')
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Condiciones Comerciales')
                    ->schema([
                        Forms\Components\Select::make('forma_pago')
                            ->label('Forma de Pago')
                            ->options([
                                'contado' => 'Contado',
                                'transferencia' => 'Transferencia',
                                'tarjeta' => 'Tarjeta',
                                'pagare' => 'Pagaré',
                                'recibo' => 'Recibo',
                            ])
                            ->default('contado'),
                        
                        Forms\Components\TextInput::make('dias_pago')
                            ->label('Días de Pago')
                            ->numeric()
                            ->maxValue(365)
                            ->default(0)
                            ->suffix('días')
                            ->extraInputAttributes(['style' => 'width: 100px']),
                        
                        Forms\Components\TextInput::make('descuento_comercial')
                            ->label('Descuento Comercial')
                            ->numeric()
                            ->maxValue(100)
                            ->default(fn() => \App\Models\Descuento::where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 0)
                            ->suffix('%')
                            ->extraInputAttributes(['style' => 'width: 120px']),
                        
                        Forms\Components\TextInput::make('limite_credito')
                            ->label('Límite de Crédito')
                            ->numeric()
                            ->maxValue(9999999999)
                            ->prefix('€')
                            ->extraInputAttributes(['style' => 'width: 140px']),
                    ])->columns(2),

                Forms\Components\Section::make('Datos Fiscales')
                    ->schema([
                        Forms\Components\Toggle::make('recargo_equivalencia')
                            ->label('Recargo de Equivalencia')
                            ->inline(false),
                        
                        Forms\Components\TextInput::make('irpf')
                            ->label('IRPF a Retener')
                            ->numeric()
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->extraInputAttributes(['style' => 'width: 120px']),
                    ])->columns(2),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('nombre_comercial')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('nif_cif')
                    ->label('NIF/CIF')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('tipos.nombre')
                    ->label('Tipos')
                    ->badge()
                    ->separator(','),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('poblacion_fiscal')
                    ->label('Población')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipos')
                    ->label('Tipo')
                    ->relationship('tipos', 'nombre')
                    ->multiple()
                    ->preload(),
                
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\DeleteAction::make()->tooltip('Borrar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerceros::route('/'),
            'create' => Pages\CreateTercero::route('/create'),
            'edit' => Pages\EditTercero::route('/{record}/edit'),
        ];
    }
}
