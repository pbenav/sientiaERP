<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturaCompraResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Models\Documento;
use App\Models\FormaPago;
use App\Models\Tercero;
use App\Services\RecibosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FacturaCompraResource extends Resource
{
    protected static ?string $model = Documento::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Facturas de Compra';
    protected static ?string $modelLabel = 'Factura de Compra';
    protected static ?string $pluralModelLabel = 'Facturas de Compra';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationGroup = 'Compras';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'factura_compra');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la Factura')->schema([
                Forms\Components\TextInput::make('numero')->label('Número')->disabled()->dehydrated(false)->columnSpan(1),
                Forms\Components\Select::make('serie')->label('Serie')->options(\App\Models\BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))->default(fn() => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')->required()->columnSpan(1),
                Forms\Components\DatePicker::make('fecha')->label('Fecha')->default(now())->required()->columnSpan(1),
                
                Forms\Components\Select::make('tercero_id')->label('Proveedor')
                    ->options(fn() => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                    ->searchable()->preload()->live()->required()
                    ->columnSpan(2)
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre_comercial')->required(),
                        Forms\Components\TextInput::make('nif_cif')->required(),
                        Forms\Components\TextInput::make('email')->email(),
                        Forms\Components\TextInput::make('telefono')->tel(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $tercero = Tercero::create($data);
                        $tercero->tipos()->attach(\App\Models\TipoTercero::where('codigo', 'PRO')->first());
                        return $tercero->id;
                    }),
                
                Forms\Components\Select::make('forma_pago_id')->label('Forma de Pago')
                    ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                    ->searchable()->preload()->default(fn() => \App\Models\FormaPago::activas()->first()?->id ?? 1)->required()
                    ->columnSpan(2),
                
                Forms\Components\Select::make('estado')->label('Estado')->options([
                    'borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'pagado' => 'Pagado', 'anulado' => 'Anulado',
                ])->default('borrador')->required()->columnSpan(2),
            ])->columns(3)->compact(),
            
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


            // SECCIÓN 5: TOTALES (solo en edición)
            Forms\Components\Section::make('Totales')
                ->schema([
                    Forms\Components\View::make('filament.components.tax-breakdown')
                        ->columnSpanFull(),
                ])->columns(3)
                ->visibleOn('edit')
                ->collapsible(),

            Forms\Components\Section::make('Observaciones')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones (visibles en el documento)')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\IconColumn::make('bloqueo_icon')->label('')->getStateUsing(fn($record) => $record->getIconoBloqueo())->color(fn($record) => $record->getColorBloqueo())->tooltip(fn($record) => $record->getMensajeBloqueoCorto()),
            Tables\Columns\TextColumn::make('numero')->label('Número')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('tercero.nombre_comercial')->label('Proveedor')->searchable()->sortable()->limit(30),
            Tables\Columns\TextColumn::make('total')->label('Total')->money('EUR')->sortable(),
            Tables\Columns\BadgeColumn::make('estado')->label('Estado')->colors([
                'secondary' => 'borrador', 'success' => 'confirmado', 'primary' => 'pagado', 'danger' => 'anulado',
            ]),
            Tables\Columns\TextColumn::make('recibos_count')->label('Recibos')
                ->counts('documentosDerivados', fn($query) => $query->where('tipo', 'recibo'))->badge()->color('gray'),
        ])->filters([
            Tables\Filters\SelectFilter::make('estado'),
            Tables\Filters\Filter::make('sin_recibos')->label('Sin recibos')
                ->query(fn ($query) => $query->whereDoesntHave('documentosDerivados', fn($q) => $q->where('tipo', 'recibo')))->toggle(),
        ])->actions([
            Tables\Actions\EditAction::make()->tooltip('Editar')->label('')->visible(fn($record) => $record->puedeEditarse()),
            Tables\Actions\Action::make('pdf')
                ->label('')
                ->tooltip('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn($record) => route('documentos.pdf', $record))
                ->openUrlInNewTab(),
            Tables\Actions\Action::make('generar_recibos')
                ->label('')
                ->tooltip('Generar Recibos')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(function ($record) {
                    return $record->estado === 'confirmado' && 
                           !Documento::where('documento_origen_id', $record->id)->where('tipo', 'recibo')->exists();
                })
                ->requiresConfirmation()
                ->action(function ($record) {
                    try {
                        $service = new RecibosService();
                        $recibos = $service->generarRecibosDesdeFactura($record);
                        Notification::make()->title('Recibos generados')->success()->body("Se han generado {$recibos->count()} recibo(s)")->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error al generar recibos')->danger()->body($e->getMessage())->send();
                    }
                }),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()->visible(fn($records) => $records && $records->every(fn($record) => $record->puedeEliminarse())),
            ]),
        ])->defaultSort('fecha', 'desc');
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
            'index' => Pages\ListFacturasCompra::route('/'),
            'create' => Pages\CreateFacturaCompra::route('/create'),
            'edit' => Pages\EditFacturaCompra::route('/{record}/edit'),
        ];
    }
}
