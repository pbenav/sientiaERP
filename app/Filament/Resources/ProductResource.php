<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Support\HasRoleAccess;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use HasRoleAccess;

    // Vendedor puede VER productos pero no crear/editar/borrar
    // Manager y Superadmin tienen acceso completo
    protected static string $viewPermission   = 'almacen.view';
    protected static string $createPermission = 'almacen.create';
    protected static string $editPermission   = 'almacen.edit';
    protected static string $deletePermission = 'almacen.delete';

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $precision = (int) \App\Models\Setting::get('intermediate_precision', 3);

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

                Forms\Components\Section::make('Precios y Rentabilidad')
                    ->schema([
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Precio de Compra')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('€')
                            ->step(1 / pow(10, $precision))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $purchasePrice = (float) $state;
                                $price = (float) $get('price');
                                $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                
                                if ($purchasePrice > 0) {
                                    $profit = $price - $purchasePrice;
                                    $profitMargin = \App\Models\Product::calculateMarginFromPrices($purchasePrice, $price, $method);
                                    
                                    $set('profit', round($profit, $precision));
                                    $set('profit_margin', round($profitMargin, 2));

                                    // Update IVA, PVP & Suggested PVP display
                                    $taxRate = (float) $get('tax_rate');
                                    $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                    $pvp = round($price * (1 + ($taxRate / 100)), $precision);
                                    $set('pvp_price', $pvp);
                                    $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                                }
                            }),
                        
                        Forms\Components\TextInput::make('profit_margin')
                            ->label('Margen (%)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $profitMargin = (float) $state;
                                $purchasePrice = (float) $get('purchase_price');
                                $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                
                                if ($purchasePrice > 0) {
                                    $price = \App\Models\Product::calculateSalePriceFromMargin($purchasePrice, $profitMargin, $method);
                                    $profit = $price - $purchasePrice;
                                    
                                    $set('profit', round($profit, $precision));
                                    $set('price', round($price, $precision));
                                    
                                    // Update IVA, PVP & Suggested PVP display
                                    $taxRate = (float) $get('tax_rate');
                                    $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                    $pvp = round($price * (1 + ($taxRate / 100)), $precision);
                                    $set('pvp_price', $pvp);
                                    $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                                }
                            }),

                        Forms\Components\TextInput::make('profit')
                            ->label('Beneficio')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('€')
                            ->step(1 / pow(10, $precision))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $profit = (float) $state;
                                $purchasePrice = (float) $get('purchase_price');
                                $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                
                                if ($purchasePrice > 0) {
                                    $price = $purchasePrice + $profit;
                                    $profitMargin = \App\Models\Product::calculateMarginFromPrices($purchasePrice, $price, $method);
                                    
                                    $set('price', round($price, $precision));
                                    $set('profit_margin', round($profitMargin, 2));
                                    
                                    // Update IVA, PVP & Suggested PVP display
                                    $taxRate = (float) $get('tax_rate');
                                    $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                    $pvp = round($price * (1 + ($taxRate / 100)), $precision);
                                    $set('pvp_price', $pvp);
                                    $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                                }
                            }),

                        Forms\Components\TextInput::make('price')
                            ->label('Precio de Venta (Base)')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('€')
                            ->step(1 / pow(10, $precision))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $price = (float) $state;
                                $purchasePrice = (float) $get('purchase_price');
                                $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                
                                if ($purchasePrice > 0) {
                                    $profit = $price - $purchasePrice;
                                    $profitMargin = \App\Models\Product::calculateMarginFromPrices($purchasePrice, $price, $method);
                                    
                                    $set('profit', round($profit, $precision));
                                    $set('profit_margin', round($profitMargin, 2));
                                }
                                
                                // Update IVA, PVP & Suggested PVP display
                                $taxRate = (float) $get('tax_rate');
                                $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                $pvp = round($price * (1 + ($taxRate / 100)), $precision);
                                $set('pvp_price', $pvp);
                                $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                            }),
                        
                        Forms\Components\Select::make('tax_rate')
                            ->label('IVA (%)')
                            ->options(\App\Models\Impuesto::where('tipo', 'iva')->where('activo', true)->pluck('nombre', 'valor'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $price = (float) $get('price');
                                $taxRate = (float) $state;
                                $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                $pvp = round($price * (1 + ($taxRate / 100)), $precision);
                                $set('pvp_price', $pvp);
                                $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')->required(),
                                Forms\Components\TextInput::make('valor')->numeric()->required()->suffix('%'),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\Impuesto::create([...$data, 'tipo' => 'iva', 'activo' => true])->valor;
                            })
                            ->default(fn() => \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21.00),

                        Forms\Components\TextInput::make('iva_amount')
                            ->label('Cuota IVA')
                            ->readOnly()
                            ->prefix('€')
                            ->step(1 / pow(10, $precision))
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get, $record) use ($precision) {
                                if ($record) {
                                    $price = (float) $record->price;
                                    $taxRate = (float) $record->tax_rate;
                                    $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                } else {
                                    $price = (float) $get('price');
                                    $taxRate = (float) $get('tax_rate');
                                    $set('iva_amount', round($price * ($taxRate / 100), $precision));
                                }
                            }),

                        Forms\Components\TextInput::make('pvp_price')
                            ->label('Precio PVP (con IVA)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('€')
                            ->step(1 / pow(10, $precision))
                            ->live(onBlur: true)
                            ->afterStateHydrated(function ($state, Forms\Set $set, $record, Forms\Get $get) use ($precision) {
                                if ($record) {
                                    $price = (float) $record->price;
                                    $taxRate = (float) $record->tax_rate;
                                    $set('pvp_price', round($price * (1 + ($taxRate / 100)), $precision));
                                } else {
                                    $price = (float) $get('price');
                                    $taxRate = (float) $get('tax_rate');
                                    $set('pvp_price', round($price * (1 + ($taxRate / 100)), $precision));
                                }
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                $pvp = (float) $state;
                                $taxRate = (float) $get('tax_rate');
                                
                                if ($pvp > 0) {
                                    $price = $pvp / (1 + ($taxRate / 100));
                                    $set('price', round($price, $precision));
                                    
                                    // Trigger price update logic
                                    $purchasePrice = (float) $get('purchase_price');
                                    $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                    
                                    if ($purchasePrice > 0) {
                                        $profit = $price - $purchasePrice;
                                        $profitMargin = \App\Models\Product::calculateMarginFromPrices($purchasePrice, $price, $method);
                                        
                                        $set('profit', round($profit, $precision));
                                        $set('profit_margin', round($profitMargin, 2));
                                    }
                                    
                                    $set('iva_amount', round($pvp - $price, $precision));
                                    $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                                }
                            }),

                        Forms\Components\TextInput::make('suggested_pvp')
                            ->label('PVP Psicológico Sugerido')
                            ->step(1 / pow(10, $precision))
                            ->readOnly()
                            ->prefix('€')
                            ->helperText('Precio psicológico inmediatamente superior al PVP actual.')
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($state, Forms\Set $set, $record, Forms\Get $get) use ($precision) {
                                $pvp = (float) $get('pvp_price');
                                if ($record && $pvp <= 0) {
                                    $pvp = (float) $record->price * (1 + ($record->tax_rate / 100));
                                }
                                if ($pvp > 0) {
                                    $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($pvp));
                                }
                            })
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('apply_suggested')
                                    ->icon('heroicon-m-check')
                                    ->tooltip('Aplicar sugerencia')
                                    ->action(function ($state, Forms\Set $set, Forms\Get $get) use ($precision) {
                                        $suggested = (float) $state;
                                        if ($suggested > 0) {
                                            $set('pvp_price', $suggested);
                                            // Activar manualmente la lógica de actualización del PVP
                                            $taxRate = (float) $get('tax_rate');
                                            $price = $suggested / (1 + ($taxRate / 100));
                                            $set('price', round($price, $precision));
                                            
                                            $purchasePrice = (float) $get('purchase_price');
                                            $method = \App\Models\Setting::get('profit_calculation_method', 'from_purchase');
                                            
                                            if ($purchasePrice > 0) {
                                                $profit = $price - $purchasePrice;
                                                $profitMargin = \App\Models\Product::calculateMarginFromPrices($purchasePrice, $price, $method);
                                                
                                                $set('profit', round($profit, $precision));
                                                $set('profit_margin', round($profitMargin, 2));
                                            }
                                            
                                            $set('iva_amount', round($suggested - $price, $precision));
                                            // Recalcular sugerencia (será la siguiente)
                                            $set('suggested_pvp', \App\Models\Product::getSuggestedPsychologicalPrice($suggested));
                                        }
                                    })
                            ),
                    ])->columns(3)->compact(),

                Forms\Components\Section::make('Stock y Visibilidad')
                    ->schema([
                        Forms\Components\TextInput::make('stock')
                            ->label('Stock')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->default(0),
                        
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ])->columns(2)->compact(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-1']),
                
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Código de Barras')
                    ->searchable()
                    ->toggleable()
                    ->extraCellAttributes(['class' => 'py-1']),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-1']),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->getStateUsing(fn ($record) => $record->price)
                    ->formatStateUsing(function ($state) {
                        $symbol = \App\Models\Setting::get('currency_symbol', '€');
                        $position = \App\Models\Setting::get('currency_position', 'suffix');
                        $precision = (int) \App\Models\Setting::get('intermediate_precision', 3);
                        $formatted = number_format($state, $precision, ',', '.');
                        return $position === 'suffix' ? "$formatted $symbol" : "$symbol $formatted";
                    })
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-1']),
                
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
                Tables\Actions\ForceDeleteAction::make()->tooltip('Eliminar Permanentemente')->label(''),
                Tables\Actions\RestoreAction::make()->tooltip('Restaurar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->compact();
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
