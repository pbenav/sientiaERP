<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturaResource\Pages;
use App\Models\Documento;
use App\Models\FormaPago;
use App\Models\Tercero;
use App\Models\Product;
use App\Services\RecibosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FacturaResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static ?string $navigationLabel = 'Facturas';

    protected static ?string $modelLabel = 'Factura';

    protected static ?string $pluralModelLabel = 'Facturas';

    protected static ?int $navigationSort = 23;

    protected static ?string $navigationGroup = 'Ventas';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'factura');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECCIÓN 1: CLIENTE
                Forms\Components\Section::make('Cliente')
                    ->schema([
                        Forms\Components\Select::make('tercero_id')
                            ->label('Cliente')
                            ->relationship('tercero', 'nombre_comercial', fn($query) => $query->clientes())
                            ->searchable(['nombre_comercial', 'nif_cif', 'codigo'])
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre_comercial')->required(),
                                Forms\Components\TextInput::make('nif_cif')->required(),
                                Forms\Components\TextInput::make('email')->email(),
                                Forms\Components\TextInput::make('telefono')->tel(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $tercero = Tercero::create($data);
                                $tercero->tipos()->attach(\App\Models\TipoTercero::where('codigo', 'CLI')->first());
                                return $tercero->id;
                            }),
                    ])->columns(1),

                // SECCIÓN 2: DATOS DE LA FACTURA
                Forms\Components\Section::make('Datos de la Factura')
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
                        
                        Forms\Components\Select::make('forma_pago_id')
                            ->label('Forma de Pago')
                            ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('La forma de pago determina los vencimientos de los recibos'),
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'confirmado' => 'Confirmado',
                                'cobrado' => 'Cobrado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                        
                        Forms\Components\Placeholder::make('subtotal_display')->label('Subtotal')->content(fn($record) => $record ? number_format($record->subtotal, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('iva_display')->label('IVA')->content(fn($record) => $record ? number_format($record->iva, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('total_display')->label('TOTAL')->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                    ])->columns(6)->compact(),

                Forms\Components\Repeater::make('lineas')
                    ->label('Líneas de la Factura')
                    ->relationship('lineas')
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema())
                    ->columns(7)
                    ->defaultItems(1)
                    ->reorderable()
                    ->addActionLabel('+ Añadir línea')
                    ->collapsible()
                    ->cloneable(),


                Forms\Components\Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),

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
                Tables\Columns\IconColumn::make('bloqueo_icon')
                    ->label('')
                    ->getStateUsing(fn($record) => $record->getIconoBloqueo())
                    ->color(fn($record) => $record->getColorBloqueo())
                    ->tooltip(fn($record) => $record->getMensajeBloqueoCorto()),
                
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'primary' => 'cobrado',
                        'danger' => 'anulado',
                    ]),
                
                Tables\Columns\TextColumn::make('recibos_count')
                    ->label('Recibos')
                    ->counts('documentosDerivados', fn($query) => $query->where('tipo', 'recibo'))
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado'),
                Tables\Filters\Filter::make('sin_recibos')
                    ->label('Sin recibos')
                    ->query(fn ($query) => $query->whereDoesntHave('documentosDerivados', fn($q) => $q->where('tipo', 'recibo')))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->puedeEditarse()),
                
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('generar_recibos')
                    ->label('Generar Recibos')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function ($record) {
                        return $record->estado === 'confirmado' && 
                               !Documento::where('documento_origen_id', $record->id)
                                   ->where('tipo', 'recibo')->exists();
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $service = new RecibosService();
                            $recibos = $service->generarRecibosDesdeFactura($record);
                            
                            Notification::make()
                                ->title('Recibos generados')
                                ->success()
                                ->body("Se han generado {$recibos->count()} recibo(s)")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al generar recibos')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                
                // TODO: Descomentar cuando se cree ReciboResource
                // Tables\Actions\Action::make('ver_recibos')
                //     ->label('Ver Recibos')
                //     ->icon('heroicon-o-eye')
                //     ->color('info')
                //     ->visible(function ($record) {
                //         return Documento::where('documento_origen_id', $record->id)
                //             ->where('tipo', 'recibo')->exists();
                //     })
                //     ->url(function ($record) {
                //         return route('filament.admin.resources.recibos.index', [
                //             'tableFilters[factura_id][value]' => $record->id
                //         ]);
                //     }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn($records) => $records && $records->every(fn($record) => $record->puedeEliminarse())),
                ]),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacturas::route('/'),
            'create' => Pages\CreateFactura::route('/create'),
            'edit' => Pages\EditFactura::route('/{record}/edit'),
        ];
    }
}
