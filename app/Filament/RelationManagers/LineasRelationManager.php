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
            Forms\Components\Select::make('product_id')
                ->label('Producto')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->columnSpan(2)
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if ($state) {
                        $product = Product::find($state);
                        if ($product) {
                            $set('codigo', $product->sku);
                            $set('descripcion', $product->name);
                            $set('precio_unitario', $product->price);
                            $set('iva', $product->tax_rate);
                        }
                    }
                }),
            
            Forms\Components\TextInput::make('codigo')
                ->label('Código')
                ->maxLength(50)
                ->columnSpan(1),
            
            // Descripción oculta - se muestra en el tooltip del producto
            Forms\Components\Hidden::make('descripcion'),
            
            Forms\Components\TextInput::make('cantidad')
                ->label('Cant.')
                ->numeric()
                ->inputMode('decimal')
                ->default(1)
                ->required()
                ->live()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[100px]'])
                ->extraInputAttributes([
                    // Global CSS handles hiding spin buttons
                ])
                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                    self::calcularLinea($set, $get)),
            
            Forms\Components\TextInput::make('precio_unitario')
                ->label('Precio')
                ->numeric()
                ->inputMode('decimal')
                ->required()
                ->live()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[120px]'])
                ->extraInputAttributes([
                    // Global CSS handles hiding spin buttons
                ])
                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                    self::calcularLinea($set, $get)),
            
            Forms\Components\TextInput::make('descuento')
                ->label('Dto.')
                ->numeric()
                ->inputMode('decimal')
                ->default(0)
                ->live()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[80px]'])
                ->extraInputAttributes([
                    // Global CSS handles hiding spin buttons
                ])
                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                    self::calcularLinea($set, $get)),
            
            Forms\Components\Select::make('iva')
                ->label('IVA %')
                ->options(\App\Models\Impuesto::where('tipo', 'iva')->where('activo', true)->pluck('nombre', 'valor'))
                ->default(function (RelationManager $livewire, Forms\Get $get) {
                    $doc = $livewire->getOwnerRecord();
                    $serie = \App\Models\BillingSerie::where('codigo', $doc->serie)->first();
                    if ($serie && $serie->devenga_iva) {
                        return $serie->ivaDefecto?->valor ?? 21;
                    }
                    return $serie && !$serie->devenga_iva ? 0 : 21;
                })
                ->required()
                ->live()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[100px]'])
                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                    self::calcularLinea($set, $get)),

            Forms\Components\Select::make('irpf')
                ->label('IRPF %')
                ->options(\App\Models\Impuesto::where('tipo', 'irpf')->where('activo', true)->pluck('nombre', 'valor'))
                ->default(function (RelationManager $livewire, Forms\Get $get) {
                    $doc = $livewire->getOwnerRecord();
                    $serie = \App\Models\BillingSerie::where('codigo', $doc->serie)->first();
                    if ($serie && $serie->sujeta_irpf) {
                        return $serie->irpfDefecto?->valor ?? 15;
                    }
                    return 0;
                })
                ->required()
                ->live()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[100px]'])
                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                    self::calcularLinea($set, $get)),
            
            Forms\Components\TextInput::make('total')
                ->label('Total')
                ->numeric()
                ->disabled()
                ->dehydrated()
                ->columnSpan(1)
                ->extraAttributes(['class' => 'max-w-[120px]'])
                ->extraInputAttributes([
                    // Global CSS handles hiding spin buttons
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
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextInputColumn::make('cantidad')
                    ->label('Cant.')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(fn ($record) => $record->documento->recalcularTotales()),
                
                Tables\Columns\TextInputColumn::make('precio_unitario')
                    ->label('Precio')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(fn ($record) => $record->documento->recalcularTotales()),
                
                Tables\Columns\TextInputColumn::make('descuento')
                    ->label('Dto.%')
                    ->type('number')
                    ->rules(['numeric', 'min:0', 'max:100'])
                    ->afterStateUpdated(fn ($record) => $record->documento->recalcularTotales()),
                
                Tables\Columns\TextInputColumn::make('iva')
                    ->label('IVA%')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(fn ($record) => $record->documento->recalcularTotales()),

                Tables\Columns\TextInputColumn::make('irpf')
                    ->label('IRPF%')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(fn ($record) => $record->documento->recalcularTotales()),
                    
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir Línea')
                    ->modalHeading('Añadir Línea al Documento')
                    ->after(fn ($livewire) => $livewire->getOwnerRecord()->recalcularTotales()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($livewire) => $livewire->getOwnerRecord()->recalcularTotales()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn ($livewire) => $livewire->getOwnerRecord()->recalcularTotales()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn ($livewire) => $livewire->getOwnerRecord()->recalcularTotales()),
                ]),
            ]);
    }
}
