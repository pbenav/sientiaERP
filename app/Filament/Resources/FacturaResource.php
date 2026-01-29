<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturaResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
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
                // SECCIÓN 1: CLIENTE Y DATOS
                Forms\Components\Grid::make(3)
                    ->schema([

                        Forms\Components\Section::make('Cliente')
                            ->schema([
                                Forms\Components\Select::make('tercero_id')
                                    ->label('Cliente')
                                    ->options(fn() => \App\Models\Tercero::clientes()->pluck('nombre_comercial', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
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
                            ])->columnSpan(1)->compact()
                            ->disabled(fn ($record) => $record && $record->estado !== 'borrador'),

                        Forms\Components\Section::make('Datos de la Factura')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('numero')
                                            ->label('Número')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Automático'),
                                        
                                        Forms\Components\Select::make('serie')
                                            ->label('Serie')
                                            ->options(\App\Models\BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))
                                            ->default(fn() => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')
                                            ->required(),

                                        Forms\Components\Toggle::make('es_rectificativa')
                                            ->label('Es Rectificativa')
                                            ->inline(false)
                                            ->live()
                                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $state ? null : $set('rectificada_id', null)),

                                        Forms\Components\Select::make('rectificada_id')
                                            ->label('Factura que rectifica')
                                            ->relationship('facturaRectificada', 'numero', function ($query, Forms\Get $get) {
                                                $terceroId = $get('tercero_id');
                                                $query->where('tipo', 'factura')
                                                      ->where('estado', 'anulado');
                                                
                                                if ($terceroId) {
                                                    $query->where('tercero_id', $terceroId);
                                                }
                                                return $query;
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(fn (Forms\Get $get) => $get('es_rectificativa'))
                                            ->visible(fn (Forms\Get $get) => $get('es_rectificativa'))
                                            ->columnSpan(2),
                                        
                                        Forms\Components\DatePicker::make('fecha')
                                            ->label('Fecha')
                                            ->default(now())
                                            ->required()
                                            ->live(),

                                        Forms\Components\Select::make('porcentaje_irpf')
                                            ->label('IRPF %')
                                            ->options(\App\Models\Impuesto::where('tipo', 'irpf')->where('activo', true)->pluck('nombre', 'valor'))
                                            ->default(0)
                                            ->live()
                                            ->afterStateUpdated(fn ($record) => $record?->recalcularTotales()),
                                        
                                        Forms\Components\Select::make('estado')
                                            ->label('Estado')
                                            ->options([
                                                'borrador' => 'Borrador',
                                                'confirmado' => 'Confirmado',
                                                'cobrado' => 'Cobrado',
                                                'anulado' => 'Anulado',
                                            ])
                                            ->default('borrador')
                                            ->required()
                                            ->disabled(fn ($record) => $record && $record->estado !== 'borrador')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        Forms\Components\Select::make('forma_pago_id')
                                            ->label('Forma de Pago')
                                            ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                                            ->searchable()
                                            ->searchable()
                                            ->preload()
                                            ->default(fn() => \App\Models\FormaPago::activas()->first()?->id ?? 1)
                                            ->required()
                                            ->columnSpan(1),
                                    ]),
                            ])->columnSpan(2)->compact()
                            ->disabled(fn ($record) => $record && $record->estado !== 'borrador'),
                    ]),

                 // SECCIÓN 3: PRODUCTOS
                Forms\Components\View::make('filament.components.document-lines-header')
                    ->columnSpanFull(),
                    
                Forms\Components\Repeater::make('lineas')
                    ->relationship()
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema())
                    ->columns(1)
                    ->defaultItems(0)
                    ->live()
                    ->hiddenLabel()
                    ->extraAttributes(['class' => 'document-lines-repeater'])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Totales')
                    ->schema([
                        Forms\Components\View::make('filament.components.tax-breakdown')
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit')
                    ->collapsible(),

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
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->getStateUsing(fn ($record) => $record->total)
                    ->formatStateUsing(function ($state) {
                        $symbol = \App\Models\Setting::get('currency_symbol', '€');
                        $position = \App\Models\Setting::get('currency_position', 'suffix');
                        $formatted = number_format($state, 2, ',', '.');
                        return $position === 'suffix' ? "$formatted $symbol" : "$symbol $formatted";
                    })
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
                Tables\Actions\ViewAction::make()->tooltip('Ver')->label(''),

                Tables\Actions\EditAction::make()->tooltip('Editar')->label('')
                    ->visible(fn($record) => $record->puedeEditarse()),
                
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                
                 Tables\Actions\Action::make('ver_recibos')
                     ->label('')
                     ->tooltip('Ver Recibos')
                     ->icon('heroicon-o-eye')
                     ->color('info')
                     ->visible(function ($record) {
                         return Documento::where('documento_origen_id', $record->id)
                             ->where('tipo', 'recibo')->exists();
                     })
                     ->url(fn($record) => route('filament.admin.resources.recibos.index', [
                         'tableFilters[factura_id][value]' => $record->id
                     ])),

                Tables\Actions\Action::make('anular')
                    ->label('')
                    ->tooltip('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Factura')
                    ->modalDescription('¿Está seguro de que desea anular esta factura? Esta acción no se puede deshacer y el número de factura quedará invalidado.')
                    ->visible(fn($record) => $record->estado === 'confirmado' && !empty($record->numero))
                    ->action(fn($record) => $record->anular()),
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
        return [
            // LineasRelationManager::class,
        ];
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
