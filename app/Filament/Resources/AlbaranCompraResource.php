<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlbaranCompraResource\Pages;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;

class AlbaranCompraResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Albaranes de Compra';

    protected static ?string $modelLabel = 'Albarán de Compra';

    protected static ?string $pluralModelLabel = 'Albaranes de Compra';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationGroup = 'Compras';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'albaran_compra');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Albarán')
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
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                    ])->columns(3),

                // En creación: Repeater estándar
                Forms\Components\Repeater::make('lineas')
                    ->label('Líneas del Albarán')
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
                Tables\Actions\Action::make('convertir_factura_compra')
                    ->label('Convertir a Factura')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn($record) => $record->estado === 'confirmado')
                    ->action(function ($record) {
                        $factura = $record->convertirA('factura_compra');
                        return redirect()->route('filament.adminadmin.resources.factura-compras.edit', $factura);
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
            'index' => Pages\ListAlbaranesCompra::route('/'),
            'create' => Pages\CreateAlbaranCompra::route('/create'),
            'edit' => Pages\EditAlbaranCompra::route('/{record}/edit'),
        ];
    }
}
