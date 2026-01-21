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
        return $form->schema(self::getLineFormSchema());
    }

    public static function getLineFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(6)
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Producto')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->columnSpan(4)
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                $product = \App\Models\Product::find($state);
                                if ($product) {
                                    $set('codigo', $product->sku);
                                    $set('descripcion', $product->name);
                                    $set('precio_unitario', $product->price);
                                    
                                    // Seguridad para IVA: Verificar si existe el tipo, sinó usar default
                                    $taxRate = number_format($product->tax_rate, 2, '.', '');
                                    $ivaExists = \App\Models\Impuesto::where('valor', $taxRate)->where('tipo', 'iva')->exists();
                                    
                                    if ($ivaExists) {
                                        $set('iva', $taxRate);
                                    } else {
                                        // Fallback al IVA por defecto (Global o de la serie)
                                        $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21.00;
                                        $set('iva', number_format($globalDefault, 2, '.', ''));
                                    }
                                    
                                    // Recalcular la línea con los nuevos valores
                                    self::calcularLinea($set, $get);
                                }
                            }
                        }),
                    
                    Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->maxLength(50)
                        ->columnSpan(2),
                    
                    Forms\Components\TextInput::make('descripcion')
                        ->label('Descripción')
                        ->maxLength(255)
                        ->columnSpan(6),
                    
                    Forms\Components\TextInput::make('cantidad')
                        ->label('Cant.')
                        ->type('text')
                        ->inputMode('decimal')
                        ->numeric()
                        ->maxValue(9999999)
                        ->required()
                        ->default(1)
                        ->columnSpan(1)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['style' => 'width: 120px'])
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                            self::calcularLinea($set, $get);
                        }),
                    
                    Forms\Components\TextInput::make('precio_unitario')
                        ->label('Precio')
                        ->type('text')
                        ->inputMode('decimal')
                        ->numeric()
                        ->maxValue(9999999999)
                        ->required()
                        ->live()
                        ->columnSpan(1)
                        ->extraInputAttributes(['style' => 'width: 110px'])
                        ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                            self::calcularLinea($set, $get)),
                    
                    Forms\Components\TextInput::make('descuento')
                        ->label('Dto.')
                        ->type('text')
                        ->inputMode('decimal')
                        ->numeric()
                        ->maxValue(100)
                        ->default(fn() => \App\Models\Descuento::where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 0)
                        ->live()
                        ->columnSpan(1)
                        ->extraInputAttributes(['style' => 'width: 110px'])
                        ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                            self::calcularLinea($set, $get)),
                    
                    Forms\Components\Select::make('iva')
                        ->label('IVA %')
                        ->options(\App\Models\Impuesto::where('tipo', 'iva')->where('activo', true)->pluck('nombre', 'valor'))
                        ->default(function (RelationManager $livewire, Forms\Get $get) {
                            $doc = $livewire->getOwnerRecord();
                            $serie = \App\Models\BillingSerie::where('codigo', $doc->serie)->first();
                            $globalDefault = \App\Models\Impuesto::where('tipo', 'iva')->where('es_predeterminado', true)->where('activo', true)->first()?->valor ?? 21;
                            
                            if ($serie && $serie->devenga_iva) {
                                return $serie->ivaDefecto?->valor ?? $globalDefault;
                            }
                            return $serie && !$serie->devenga_iva ? 0 : $globalDefault;
                        })
                        ->required()
                        ->live()
                        ->columnSpan(1)
                        ->extraInputAttributes(['style' => 'width: 120px'])
                        ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                            self::calcularLinea($set, $get)),

                    Forms\Components\TextInput::make('total')
                        ->label('Total')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1)
                        ->extraInputAttributes(['style' => 'width: 140px']),
                ]),
        ];
    }





    protected static function calcularLinea(Forms\Set $set, Forms\Get $get): void
    {
        $cantidad = floatval($get('cantidad') ?? 0);
        $precio = floatval($get('precio_unitario') ?? 0);
        $descuento = floatval($get('descuento') ?? 0);
        $iva = floatval($get('iva') ?? 0);

        $subtotal = $cantidad * $precio;
        
        if ($descuento > 0) {
            $subtotal = $subtotal * (1 - ($descuento / 100));
        }

        $importeIva = $subtotal * ($iva / 100);
        $total = $subtotal + $importeIva;

        $set('subtotal', round($subtotal, 2));
        $set('importe_iva', round($importeIva, 2));
        $set('total', round($total, 2));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextInputColumn::make('cantidad')
                    ->label('Cant.')
                    ->type('text')
                    ->inputMode('decimal')
                    ->extraHeaderAttributes(['style' => 'width: 100px; min-width: 100px; max-width: 100px'])
                    ->extraAttributes(['style' => 'width: 100px; min-width: 100px; max-width: 100px'])
                    ->extraInputAttributes([
                        'style' => 'text-align: center; padding: 4px;',
                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }', // Prevenir submit del formulario principal
                        'onfocus' => 'this.select()',
                    ])
                    ->sortable()
                    ->disabled(fn($livewire) => $livewire->getOwnerRecord()->estado !== 'borrador')
                    ->rules(['required', 'numeric', 'min:0', 'max:9999999'])
                    ->afterStateUpdated(function ($record, $livewire) {
                        $record->documento->recalcularTotales();
                        $livewire->dispatch('refresh-document-totals');
                    }),
                
                Tables\Columns\TextInputColumn::make('precio_unitario')
                    ->label('Precio')
                    ->type('text')
                    ->inputMode('decimal')
                    ->extraHeaderAttributes(['style' => 'width: 110px; min-width: 110px; max-width: 110px'])
                    ->extraAttributes(['style' => 'width: 110px; min-width: 110px; max-width: 110px'])
                    ->extraInputAttributes([
                        'style' => 'text-align: right; padding: 4px;',
                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }',
                        'onfocus' => 'this.select()',
                    ])
                    ->sortable()
                    ->disabled(fn($livewire) => $livewire->getOwnerRecord()->estado !== 'borrador')
                    ->rules(['required', 'numeric', 'min:0', 'max:9999999999'])
                    ->afterStateUpdated(function ($record, $livewire) {
                        $record->documento->recalcularTotales();
                        $livewire->dispatch('refresh-document-totals');
                    }),
                
                Tables\Columns\TextInputColumn::make('descuento')
                    ->label('Dto.%')
                    ->type('text')
                    ->inputMode('decimal')
                    ->extraHeaderAttributes(['style' => 'width: 70px; min-width: 70px; max-width: 70px'])
                    ->extraAttributes(['style' => 'width: 70px; min-width: 70px; max-width: 70px'])
                    ->extraInputAttributes([
                        'style' => 'text-align: center; padding: 4px;',
                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }',
                        'onfocus' => 'this.select()',
                    ])
                    ->sortable()
                    ->disabled(fn($livewire) => $livewire->getOwnerRecord()->estado !== 'borrador')
                    ->rules(['numeric', 'min:0', 'max:100'])
                    ->afterStateUpdated(function ($record, $livewire) {
                        $record->documento->recalcularTotales();
                        $livewire->dispatch('refresh-document-totals');
                    }),
                
                Tables\Columns\TextInputColumn::make('iva')
                    ->label('IVA%')
                    ->type('text')
                    ->inputMode('decimal')
                    ->extraHeaderAttributes(['style' => 'width: 90px; min-width: 90px; max-width: 90px'])
                    ->extraAttributes(['style' => 'width: 90px; min-width: 90px; max-width: 90px'])
                    ->extraInputAttributes([
                        'style' => 'text-align: center; padding: 4px;',
                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }',
                        'onfocus' => 'this.select()',
                    ])
                    ->sortable()
                    ->disabled(fn($livewire) => $livewire->getOwnerRecord()->estado !== 'borrador')
                    ->rules(['required', 'numeric', 'min:0', 'max:100'])
                    ->afterStateUpdated(function ($record, $livewire) {
                        $record->documento->recalcularTotales();
                        $livewire->dispatch('refresh-document-totals');
                    }),
                    
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->extraHeaderAttributes(['style' => 'width: 140px; min-width: 140px; max-width: 140px'])
                    ->extraAttributes(['style' => 'width: 140px; min-width: 140px; max-width: 140px'])
                    ->alignRight()
                    ->getStateUsing(fn ($record) => $record->total)
                    ->formatStateUsing(function ($state) {
                        $symbol = \App\Models\Setting::get('currency_symbol', '€');
                        $position = \App\Models\Setting::get('currency_position', 'suffix');
                        $formatted = number_format($state, 2, ',', '.');
                        return $position === 'suffix' ? "$formatted $symbol" : "$symbol $formatted";
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir Línea')
                    ->modalHeading('Añadir Línea al Documento')
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->estado === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->estado === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->estado === 'borrador')
                    ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($livewire) => $livewire->getOwnerRecord()->estado === 'borrador')
                        ->after(fn ($livewire) => $livewire->dispatch('refresh-document-totals')),
                ]),
            ]);
    }
}
