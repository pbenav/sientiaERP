<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresupuestoResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;

class PresupuestoResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Presupuestos';

    protected static ?string $modelLabel = 'Presupuesto';

    protected static ?string $pluralModelLabel = 'Presupuestos';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationGroup = 'Ventas';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'presupuesto');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Presupuesto')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente'),
                        
                        Forms\Components\Select::make('serie')
                            ->label('Serie')
                            ->options(\App\Models\BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))
                            ->default(fn() => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')
                            ->required(),
                        
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\DatePicker::make('fecha_validez')
                            ->label('Válido hasta')
                            ->default(fn() => now()->addDays((int)\App\Models\Setting::get('presupuesto_validez_dias', 15)))
                            ->required(),
                        
                        Forms\Components\Select::make('tercero_id')
                            ->label('Cliente')
                            ->options(fn() => \App\Models\Tercero::clientes()->pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre_comercial')
                                    ->required(),
                                Forms\Components\TextInput::make('nif_cif')
                                    ->required(),
                            ]),
                        
                        Forms\Components\Select::make('forma_pago_id')
                            ->label('Forma de Pago')
                            ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                            ->searchable()
                            ->preload()
                            ->default(1)
                            ->required(),
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'confirmado' => 'Confirmado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                    ])->columns(3),

                // SECCIÓN 2: PRODUCTOS (Movido a RelationManager)
                // Forms\Components\View::make('filament.components.document-lines')
                //     ->columnSpanFull(),

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
                        
                        Forms\Components\Textarea::make('observaciones_internas')
                            ->label('Observaciones internas (no visibles)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
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
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Cliente')
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
                        'danger' => 'anulado',
                    ]),
                
                Tables\Columns\TextColumn::make('fecha_validez')
                    ->label('Válido hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado' => 'Anulado',
                    ]),
                
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde'),
                        Forms\Components\DatePicker::make('hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q, $date) => $q->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'], fn($q, $date) => $q->whereDate('fecha', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('convertir_pedido')
                    ->label('')
                    ->tooltip('Convertir a Pedido')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->visible(fn($record) => $record->estado === 'confirmado')
                    ->action(function ($record) {
                        $pedido = $record->convertirA('pedido');
                        return redirect()->route('filament.admin.resources.pedidos.edit', $pedido);
                    }),
                Tables\Actions\DeleteAction::make()->tooltip('Borrar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            LineasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPresupuestos::route('/'),
            'create' => Pages\CreatePresupuesto::route('/create'),
            'edit' => Pages\EditPresupuesto::route('/{record}/edit'),
        ];
    }
}
