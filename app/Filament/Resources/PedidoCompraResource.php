<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoCompraResource\Pages;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;

class PedidoCompraResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Pedidos de Compra';

    protected static ?string $modelLabel = 'Pedido de Compra';

    protected static ?string $pluralModelLabel = 'Pedidos de Compra';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationGroup = 'Compras';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'pedido_compra');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Pedido de Compra')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente'),
                        
                        Forms\Components\Select::make('serie')
                            ->label('Serie')
                            ->options(['A' => 'Serie A', 'B' => 'Serie B'])
                            ->default('A')
                            ->required(),
                        
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\DatePicker::make('fecha_entrega')
                            ->label('Fecha de Recepción')
                            ->default(now()->addDays(7)),
                        
                        Forms\Components\Select::make('tercero_id')
                            ->label('Proveedor')
                            ->relationship('tercero', 'nombre_comercial', fn($query) => $query->proveedores())
                            ->searchable()
                            ->preload()
                            ->required(),
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'confirmado' => 'Confirmado',
                                'parcial' => 'Parcial',
                                'completado' => 'Completado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                    ])->columns(3),


                // En creación: Repeater estándar (para añadir productos inmediatamente)
                Forms\Components\Repeater::make('lineas')
                    ->label('Líneas del Pedido')
                    ->relationship('lineas')
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema())
                    ->columns(5)
                    ->visibleOn('create')
                    ->columnSpanFull(),

                Forms\Components\Section::make('Totales')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('Subtotal')
                            ->content(fn($record) => $record ? number_format($record->subtotal, 2, ',', '.') . ' €' : '0,00 €'),
                        
                        Forms\Components\Placeholder::make('iva_display')
                            ->label('IVA')
                            ->content(fn($record) => $record ? number_format($record->iva, 2, ',', '.') . ' €' : '0,00 €'),
                        
                        Forms\Components\Placeholder::make('total_display')
                            ->label('TOTAL')
                            ->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €'),
                    ])->columns(3),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones (visibles en el documento)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'primary' => 'completado',
                        'warning' => 'parcial',
                        'danger' => 'anulado',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('convertir_albaran_compra')
                    ->label('Convertir a Albarán')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn($record) => in_array($record->estado, ['confirmado', 'parcial']))
                    ->action(function ($record) {
                        $albaran = $record->convertirA('albaran_compra');
                        // Redirigir a albaranes de compra (necesitaremos esta ruta)
                        return redirect()->route('filament.adminadmin.resources.albaran-compras.edit', $albaran);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            \App\Filament\RelationManagers\LineasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPedidosCompra::route('/'),
            'create' => Pages\CreatePedidoCompra::route('/create'),
            'edit' => Pages\EditPedidoCompra::route('/{record}/edit'),
        ];
    }
}
