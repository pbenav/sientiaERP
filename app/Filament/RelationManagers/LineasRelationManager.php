<?php

namespace App\Filament\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class LineasRelationManager extends RelationManager
{
    protected static string $relationship = 'lineas';

    protected static ?string $title = 'Líneas del Documento';

    protected static ?string $modelLabel = 'Línea';

    protected static ?string $pluralModelLabel = 'Líneas';

    public function form(Form $form): Form
    {
        $doc = $this->getOwnerRecord();
        $isLabel = $doc && $doc->tipo === 'etiqueta';
        
        return $form->schema(self::getLineFormSchema($isLabel));
    }

    public static function getLineFormSchema(bool $isLabel = false): array
    {
        return [
            Forms\Components\Grid::make(['default' => 12])
                ->schema([
                    // CÓDIGO (Span 2) - Sufficient for the dropdown arrow
                    Forms\Components\Select::make('codigo')
                        ->hiddenLabel()
                        ->searchable()
                        ->options(function () {
                            return \App\Models\Product::query()
                                ->orderBy('sku')
                                ->limit(50)
                                ->pluck('sku', 'sku');
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return \App\Models\Product::where('sku', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('sku', 'sku');
                        })
                        ->createOptionForm([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('sku')
                                        ->label('Código/SKU')
                                        ->required()
                                        ->unique('products', 'sku'),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre del Producto')
                                        ->required(),
                                    Forms\Components\TextInput::make('price')
                                        ->label('Precio Venta (IVA Incl.)')
                                        ->numeric()
                                        ->required()
                                        ->default(0),
                                    Forms\Components\TextInput::make('tax_rate')
                                        ->label('IVA %')
                                        ->numeric()
                                        ->default(21)
                                        ->required(),
                                ])
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['active'] = true;
                            $product = \App\Models\Product::create($data);
                            return $product->sku;
                        })
                        ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action->modalHeading('Nuevo Producto')->label('Nuevo Producto'))
                        ->live()
                        ->nullable(false)
                        ->placeholder('Cód.')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                            if ($state) {
                                try {
                                    // Buscar producto por SKU o código de barras
                                    $producto = \App\Models\Product::where('sku', $state)
                                        ->orWhere('barcode', $state)
                                        ->first();
                                    
                                    if ($producto) {
                                        $set('product_id', $producto->id);
                                        $set('descripcion', $producto->name);

                                        // Determine price based on document type
                                        $doc = null;
                                        if (isset($livewire)) {
                                            $doc = method_exists($livewire, 'getOwnerRecord') 
                                                ? $livewire->getOwnerRecord() 
                                                : ($livewire->record ?? null);
                                        }
                                        
                                        $isPurchase = $doc && str_ends_with($doc->tipo, '_compra');
                                        
                                        if ($isPurchase) {
                                            $cost = (float)($producto->metadata['purchase_price'] ?? 0);
                                            $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($cost, 2));
                                        } else {
                                            // Sale or other: convert PVP to base price
                                            $taxRate = (float)$producto->tax_rate;
                                            $priceWithTax = (float)$producto->price;
                                            $basePrice = $priceWithTax / (1 + ($taxRate / 100));
                                            $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($basePrice, 2));
                                        }
                                        
                                        // IVA del producto o por defecto
                                        $taxRate = number_format((float)$producto->tax_rate, 2, '.', '');
                                        $ivaExists = \App\Models\Impuesto::where('valor', (float)$taxRate)
                                            ->where('tipo', 'iva')
                                            ->exists();
                                        
                                        if ($ivaExists) {
                                            $set('iva', $taxRate);
                                        } else {
                                            $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')
                                                ->where('es_predeterminado', true)
                                                ->where('activo', true)
                                                ->first()?->valor ?? 21.00;
                                            $set('iva', number_format((float)$globalDefault, 2, '.', ''));
                                        }

                                        self::calcularLinea($set, $get);
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Error in coding afterStateUpdated: ' . $e->getMessage());
                                }
                            }
                        })
                        ->extraAttributes(['class' => 'hide-select-clear'])
                        ->columnSpan(2),
                    
                    // DESCRIPCIÓN (Span 5) - Increased for long names
                    Forms\Components\Select::make('descripcion')
                        ->hiddenLabel()
                        ->required()
                        ->searchable()
                        ->options(function () {
                            return \App\Models\Product::query()
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'name');
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return \App\Models\Product::where('name', 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('name', 'name');
                        })
                        ->createOptionForm([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('sku')
                                        ->label('Código/SKU')
                                        ->required()
                                        ->unique('products', 'sku'),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre del Producto')
                                        ->required(),
                                    Forms\Components\TextInput::make('price')
                                        ->label('Precio Venta (IVA Incl.)')
                                        ->numeric()
                                        ->required()
                                        ->default(0),
                                    Forms\Components\TextInput::make('tax_rate')
                                        ->label('IVA %')
                                        ->numeric()
                                        ->default(21)
                                        ->required(),
                                ])
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['active'] = true;
                            $product = \App\Models\Product::create($data);
                            return $product->name;
                        })
                        ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action->modalHeading('Nuevo Producto')->label('Nuevo Producto'))
                        ->live()
                        ->nullable(false)
                        ->placeholder('Descripción')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                            if ($state) {
                                try {
                                    // Buscar producto por nombre
                                    $producto = \App\Models\Product::where('name', $state)->first();
                                    if ($producto) {
                                        $set('product_id', $producto->id);
                                        $set('codigo', $producto->sku);
                                        
                                        // Determine price based on document type
                                        $doc = null;
                                        if (isset($livewire)) {
                                            $doc = method_exists($livewire, 'getOwnerRecord') 
                                                ? $livewire->getOwnerRecord() 
                                                : ($livewire->record ?? null);
                                        }
                                        
                                        $isPurchase = $doc && str_ends_with($doc->tipo, '_compra');
                                        
                                        if ($isPurchase) {
                                            $cost = (float)($producto->metadata['purchase_price'] ?? 0);
                                            $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($cost, 2));
                                        } else {
                                            // Sale or other: convert PVP to base price
                                            $taxRate = (float)$producto->tax_rate;
                                            $priceWithTax = (float)$producto->price;
                                            $basePrice = $priceWithTax / (1 + ($taxRate / 100));
                                            $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($basePrice, 2));
                                        }
                                        
                                        // IVA del producto o por defecto
                                        $taxRate = number_format((float)$producto->tax_rate, 2, '.', '');
                                        $ivaExists = \App\Models\Impuesto::where('valor', (float)$taxRate)
                                            ->where('tipo', 'iva')
                                            ->exists();
                                        
                                        if ($ivaExists) {
                                            $set('iva', $taxRate);
                                        } else {
                                            $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')
                                                ->where('es_predeterminado', true)
                                                ->where('activo', true)
                                                ->first()?->valor ?? 21.00;
                                            $set('iva', number_format((float)$globalDefault, 2, '.', ''));
                                        }

                                        self::calcularLinea($set, $get);
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Error in description afterStateUpdated: ' . $e->getMessage());
                                }
                            }
                        })
                        ->extraAttributes(['class' => 'hide-select-clear'])
                        ->columnSpan(5),
                    
                    Forms\Components\Hidden::make('product_id'),
                    
                    // CANTIDAD (Span 1) - Sufficient for small numbers
                    Forms\Components\TextInput::make('cantidad')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(9999999)
                        ->required()
                        ->default(1)
                        ->columnSpan(1)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 0))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // PRECIO (Span 1) - Sufficient for unit prices
                    Forms\Components\TextInput::make('precio_unitario')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(9999999999)
                        ->required()
                        ->default(0)
                        ->live(onBlur: true)
                        ->columnSpan(1)
                        ->hidden($isLabel)
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // DESCUENTO (Span 1) - Minimal space
                    Forms\Components\TextInput::make('descuento')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(100)
                        ->default(0)
                        ->live(onBlur: true)
                        ->columnSpan(1)
                        ->hidden($isLabel)
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // SUBVTOTAL
 
                    // IMPORTE (Span 1) - Sufficient for totals
                    Forms\Components\TextInput::make('subtotal')
                        ->hiddenLabel()
                        ->extraInputAttributes(['readonly' => true, 'class' => 'text-right'])
                        ->default(0)
                        ->dehydrated() 
                        ->columnSpan(1) 
                        ->hidden($isLabel)
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state)),

                    // IVA (Span 1) - Read only, simplified
                    Forms\Components\TextInput::make('iva')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated() // Keep sending value
                        ->formatStateUsing(fn ($state) => $state ? $state . '%' : '')
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->extraInputAttributes(['class' => 'text-center', 'style' => 'background-color: transparent; border: none; box-shadow: none; padding: 0;'])
                        ->default(function ($livewire) {
                            $doc = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : ($livewire->record ?? null);
                            if (!$doc) return 21;
                            $serie = \App\Models\BillingSerie::where('codigo', $doc->serie ?? 'A')->first();
                            $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21;
                            if ($serie && $serie->devenga_iva) return $serie->ivaDefecto?->valor ?? $globalDefault;
                            return $serie && !$serie->devenga_iva ? 0 : $globalDefault;
                        })
                        ->columnSpan(1)
                        ->hidden($isLabel),

                    Forms\Components\Hidden::make('total')->default(0),
                    Forms\Components\Hidden::make('importe_iva')->default(0),
                    Forms\Components\Hidden::make('importe_recargo_equivalencia')->default(0),
                    Forms\Components\Hidden::make('recargo_equivalencia')->default(0),
                    Forms\Components\Hidden::make('irpf')->default(0),
                    Forms\Components\Hidden::make('importe_irpf')->default(0),
                ]),
        ];
    }





    public static function calcularLinea(Forms\Set $set, Forms\Get $get): void
    {
        try {
            $cantidad = \App\Helpers\NumberFormatHelper::parseNumber($get('cantidad') ?? '0');
            $precio = \App\Helpers\NumberFormatHelper::parseNumber($get('precio_unitario') ?? '0');
            $descuento = \App\Helpers\NumberFormatHelper::parseNumber($get('descuento') ?? '0');
            $iva = \App\Helpers\NumberFormatHelper::parseNumber($get('iva') ?? '0');

            $subtotal = $cantidad * $precio;
            
            if ($descuento > 0) {
                // Ensure discount doesn't exceed 100%
                $disc = min(100, max(0, $descuento));
                $subtotal = $subtotal * (1 - ($disc / 100));
            }

            $importeIva = $subtotal * ($iva / 100);
            $total = $subtotal + $importeIva;

            $set('subtotal', \App\Helpers\NumberFormatHelper::formatNumber($subtotal, 2));
            $set('importe_iva', round($importeIva, 3));
            $set('total', round($total, 3));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error calculating document line: ' . $e->getMessage());
            // Safe fallbacks to avoid blank/null errors in UI
            $set('subtotal', '0,00');
            $set('importe_iva', 0);
            $set('total', 0);
        }
    }

    public function table(Table $table): Table
    {
        $doc = $this->getOwnerRecord();
        $isLabel = $doc && $doc->tipo === 'etiqueta';
        
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                // CÓDIGO como primera columna
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->extraHeaderAttributes(['style' => 'width: 120px; min-width: 120px; max-width: 120px'])
                    ->extraAttributes(['style' => 'width: 120px; min-width: 120px; max-width: 120px'])
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->extraHeaderAttributes(['style' => 'width: 100px; min-width: 100px; max-width: 100px'])
                    ->extraAttributes(['style' => 'width: 100px; min-width: 100px; max-width: 100px'])
                    ->sortable()
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 0))
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('precio_unitario')
                    ->label('Precio')
                    ->extraAttributes(['style' => 'width: 110px; min-width: 110px; max-width: 110px'])
                    ->sortable()
                    ->visible(!$isLabel)
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('descuento')
                    ->label('Dto.%')
                    ->extraAttributes(['style' => 'width: 70px; min-width: 70px; max-width: 70px'])
                    ->sortable()
                    ->visible(!$isLabel)
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                    ->alignCenter(),

                // Mostramos Base Imponible en lugar de Total con IVA
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Base')
                    ->extraAttributes(['style' => 'width: 120px; min-width: 120px; max-width: 120px'])
                    ->sortable()
                    ->visible(!$isLabel)
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('iva')
                    ->label('IVA%')
                    ->badge() // Badge para resaltar que es un tipo impositivo
                    ->color('info')
                    ->extraAttributes(['style' => 'width: 90px; min-width: 90px; max-width: 90px'])
                    ->sortable()
                    ->visible(!$isLabel)
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 0) . '%')
                    ->alignCenter(),
                    
                // Ocultamos la columna Total porque se desgloza al final
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir Línea')
                    ->modalHeading('Añadir Línea al Documento')
                    ->visible(fn ($livewire) => strtolower($livewire->getOwnerRecord()->estado) === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($livewire) => strtolower($livewire->getOwnerRecord()->estado) === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($livewire) => strtolower($livewire->getOwnerRecord()->estado) === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($livewire) => strtolower($livewire->getOwnerRecord()->estado) === 'borrador')
                        ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
                ]),
            ]);
    }
}
