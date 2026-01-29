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
            Forms\Components\Grid::make(12)
                ->schema([
                    // CÓDIGO (Span 2)
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
                        ->live()
                        ->nullable(false)
                        ->placeholder('Código')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                // Buscar producto por SKU o código de barras
                                $producto = \App\Models\Product::where('sku', $state)
                                    ->orWhere('barcode', $state)
                                    ->first();
                                if ($producto) {
                                    $set('product_id', $producto->id);
                                    $set('descripcion', $producto->name);
                                    $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($producto->price, 2));
                                    
                                    // IVA del producto o por defecto
                                    $taxRate = number_format($producto->tax_rate, 2, '.', '');
                                    $ivaExists = \App\Models\Impuesto::where('valor', $taxRate)->where('tipo', 'iva')->exists();
                                    
                                    if ($ivaExists) {
                                        $set('iva', $taxRate);
                                    } else {
                                        $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')
                                            ->where('es_predeterminado', true)
                                            ->where('activo', true)
                                            ->first()?->valor ?? 21.00;
                                        $set('iva', number_format($globalDefault, 2, '.', ''));
                                    }

                                    self::calcularLinea($set, $get);
                                }
                            }
                        })
                        ->columnSpan(2),
                    
                    // DESCRIPCIÓN (Span 4)
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
                        ->live()
                        ->nullable(false)
                        ->placeholder('Descripción')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                // Buscar producto por nombre
                                $producto = \App\Models\Product::where('name', $state)->first();
                                if ($producto) {
                                    $set('product_id', $producto->id);
                                    // Solo actualizar código si está vacío
                                    if (!$get('codigo')) {
                                        $set('codigo', $producto->sku);
                                    }
                                    $set('precio_unitario', \App\Helpers\NumberFormatHelper::formatNumber($producto->price, 2));
                                    
                                    // IVA del producto o por defecto
                                    $taxRate = number_format($producto->tax_rate, 2, '.', '');
                                    $ivaExists = \App\Models\Impuesto::where('valor', $taxRate)->where('tipo', 'iva')->exists();
                                    
                                    if ($ivaExists) {
                                        $set('iva', $taxRate);
                                    } else {
                                        $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')
                                            ->where('es_predeterminado', true)
                                            ->where('activo', true)
                                            ->first()?->valor ?? 21.00;
                                        $set('iva', number_format($globalDefault, 2, '.', ''));
                                    }

                                    self::calcularLinea($set, $get);
                                }
                            }
                        })
                        ->columnSpan(5),
                    
                    Forms\Components\Hidden::make('product_id'),
                    
                    // CANTIDAD (Span 1)
                    Forms\Components\TextInput::make('cantidad')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(9999999)
                        ->required()
                        ->default(1)
                        ->columnSpan(1)
                        ->live(onBlur: true)
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 0))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // PRECIO (Span 1)
                    Forms\Components\TextInput::make('precio_unitario')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(9999999999)
                        ->required()
                        ->live(onBlur: true)
                        ->columnSpan(1)
                        ->visible(!$isLabel)
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // DESCUENTO (Span 1)
                    Forms\Components\TextInput::make('descuento')
                        ->hiddenLabel()
                        ->type('text')
                        ->inputMode('decimal')
                        ->maxValue(100)
                        ->default(fn() => \App\Models\Descuento::where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 0)
                        ->live(onBlur: true)
                        ->columnSpan(1)
                        ->visible(!$isLabel)
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2))
                        ->dehydrateStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::parseNumber($state))
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    // IVA HIDDEN (instead of select)
                    Forms\Components\Hidden::make('iva')
                        ->default(function ($livewire, Forms\Get $get) {
                            $doc = method_exists($livewire, 'getOwnerRecord') 
                                ? $livewire->getOwnerRecord() 
                                : ($livewire->record ?? null);
                            
                            if (!$doc) return 21;

                            $serie = \App\Models\BillingSerie::where('codigo', $doc->serie ?? 'A')->first();
                            $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21;
                            
                            if ($serie && $serie->devenga_iva) {
                                return $serie->ivaDefecto?->valor ?? $globalDefault;
                            }
                            return $serie && !$serie->devenga_iva ? 0 : $globalDefault;
                        }),
 
                    Forms\Components\TextInput::make('subtotal')
                        ->hiddenLabel()
                        ->extraInputAttributes(['readonly' => true]) // Readonly en vez de disabled para mejor reactividad visual
                        ->dehydrated() // Permitir que viaje en el estado para el calculador
                        ->columnSpan(1)
                        ->visible(!$isLabel)
                        ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatNumber($state, 2)),

                    Forms\Components\Placeholder::make('iva_display')
                        ->hiddenLabel()
                        ->content(fn ($get) => number_format((float)$get('iva'), 0) . '%')
                        ->extraAttributes(['class' => 'text-center pt-2', 'style' => 'font-size: 0.85rem; font-weight: 500; color: #6b7280;'])
                        ->columnSpan(1)
                        ->visible(!$isLabel),
                ]),
        ];
    }





    public static function calcularLinea(Forms\Set $set, Forms\Get $get): void
    {
        $cantidad = \App\Helpers\NumberFormatHelper::parseNumber($get('cantidad') ?? '0');
        $precio = \App\Helpers\NumberFormatHelper::parseNumber($get('precio_unitario') ?? '0');
        $descuento = \App\Helpers\NumberFormatHelper::parseNumber($get('descuento') ?? '0');
        $iva = \App\Helpers\NumberFormatHelper::parseNumber($get('iva') ?? '0');

        $subtotal = $cantidad * $precio;
        
        if ($descuento > 0) {
            $subtotal = $subtotal * (1 - ($descuento / 100));
        }

        $importeIva = $subtotal * ($iva / 100);
        $total = $subtotal + $importeIva;

        $set('subtotal', \App\Helpers\NumberFormatHelper::formatNumber($subtotal, 2));
        $set('importe_iva', round($importeIva, 3));
        $set('total', round($total, 3));
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
