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
            \App\Filament\Support\DocumentFormFactory::terceroSection('Proveedor', 'PRO'),
            
            \App\Filament\Support\DocumentFormFactory::detailsSection('Datos de la Factura', [
                Forms\Components\Select::make('forma_pago_id')->label('Forma de Pago')
                    ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                    ->searchable()->preload()->default(fn() => \App\Models\FormaPago::activas()->first()?->id ?? 1)->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('codigo')->required()->maxLength(50),
                        Forms\Components\TextInput::make('nombre')->required()->maxLength(100),
                        Forms\Components\Select::make('tipo')->options(['transferencia' => 'Transferencia', 'contado' => 'Contado', 'recibo_bancario' => 'Recibo'])->required()->default('transferencia'),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $fp = \App\Models\FormaPago::create($data);
                        $fp->tramos()->create(['dias' => 0, 'porcentaje' => 100]);
                        return $fp->id;
                    }),
                Forms\Components\Select::make('estado')->label('Estado')->options([
                    'borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'pagado' => 'Pagado', 'anulado' => 'Anulado',
                ])->default('borrador')->required(),
            ], [
                'disable_numero' => false,
                'numero_required' => true,
                'numero_placeholder' => 'Número de factura del proveedor',
                'exclude_estado' => true,
            ]),

            ...\App\Filament\Support\DocumentFormFactory::linesSection(),

            \App\Filament\Support\DocumentFormFactory::totalsSection()
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
            Tables\Columns\TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))->sortable(),
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
