<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReciboCompraResource\Pages;
use App\Models\Documento;
use App\Models\Tercero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReciboCompraResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';

    protected static ?string $navigationLabel = 'Recibos de Compra';

    protected static ?string $modelLabel = 'Recibo de Compra';

    protected static ?string $pluralModelLabel = 'Recibos de Compra';

    protected static ?int $navigationSort = 14;

    protected static ?string $navigationGroup = 'Compras';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'recibo_compra');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Recibo')
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
                            ->label('Fecha Emisión')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\DatePicker::make('vencimiento')
                            ->label('Vencimiento')
                            ->default(now()->addDays(30))
                            ->required(),
                        
                        Forms\Components\Select::make('tercero_id')
                            ->label('Proveedor')
                            ->options(fn() => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'pagado' => 'Pagado',
                                'impagado' => 'Impagado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('pendiente')
                            ->required(),
                        
                        Forms\Components\TextInput::make('total')
                            ->label('Importe Total')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                            
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(3),
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
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'pagado',
                        'danger' => ['impagado', 'anulado'],
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecibosCompra::route('/'),
            'create' => Pages\CreateReciboCompra::route('/create'),
            'edit' => Pages\EditReciboCompra::route('/{record}/edit'),
        ];
    }
}
