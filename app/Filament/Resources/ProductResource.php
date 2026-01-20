<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Producto')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Producto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('sku')
                            ->label('Referencia/SKU')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de Barras')
                            ->maxLength(255)
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpan(2),
                    ])->columns(3)->compact(),

                Forms\Components\Section::make('Precios y Stock')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Precio (sin IVA)')
                            ->required()
                            ->type('text')
                            ->inputMode('decimal')
                            ->numeric()
                            ->maxValue(9999999999)
                            ->prefix('€')
                            ->extraInputAttributes(['style' => 'width: 140px']),
                        
                        Forms\Components\TextInput::make('tax_rate')
                            ->label('IVA (%)')
                            ->required()
                            ->type('text')
                            ->inputMode('decimal')
                            ->numeric()
                            ->maxValue(100)
                            ->default(fn() => \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21.00)
                            ->suffix('%')
                            ->extraInputAttributes(['style' => 'width: 120px']),
                        
                        Forms\Components\TextInput::make('stock')
                            ->label('Stock')
                            ->required()
                            ->type('text')
                            ->inputMode('decimal')
                            ->numeric()
                            ->maxValue(9999999)
                            ->extraInputAttributes(['style' => 'width: 120px'])
                            ->default(0),
                        
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ])->columns(4)->compact(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Código de Barras')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->getStateUsing(fn ($record) => $record->price)
                    ->formatStateUsing(function ($state) {
                        $symbol = \App\Models\Setting::get('currency_symbol', '€');
                        $position = \App\Models\Setting::get('currency_position', 'suffix');
                        $formatted = number_format($state, 2, ',', '.');
                        return $position === 'suffix' ? "$formatted $symbol" : "$symbol $formatted";
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('IVA')
                    ->suffix('%')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success',
                    }),
                
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
                
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stock bajo')
                    ->query(fn ($query) => $query->where('stock', '<', 10)),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\DeleteAction::make()->tooltip('Borrar')->label(''),
                Tables\Actions\ForceDeleteAction::make()->tooltip('Eliminar Permanentemente')->label('')->tooltip('Borrar')->label(''),
                Tables\Actions\RestoreAction::make()->tooltip('Restaurar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
