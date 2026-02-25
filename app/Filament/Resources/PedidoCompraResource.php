<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoCompraResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Filament\Support\HasRoleAccess;
use App\Models\Documento;
use App\Models\FormaPago;
use App\Models\Tercero;
use App\Models\Product;
use App\Services\AgrupacionDocumentosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PedidoCompraResource extends Resource
{
    use HasRoleAccess;

    protected static string $viewPermission   = 'compras.view';
    protected static string $createPermission = 'compras.create';
    protected static string $editPermission   = 'compras.edit';
    protected static string $deletePermission = 'compras.delete';

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
        return $form->schema([
            \App\Filament\Support\DocumentFormFactory::terceroSection('Proveedor', 'PRO'),
            
            \App\Filament\Support\DocumentFormFactory::detailsSection('Datos del Pedido de Compra', [
                Forms\Components\DatePicker::make('fecha_entrega')->label('Fecha de Recepción')->default(now()->addDays(7)),
                
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
                    'borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'parcial' => 'Parcial',
                    'completado' => 'Completado', 'anulado' => 'Anulado',
                ])->default('borrador')->required(),
            ], [
                'exclude_estado' => true,
            ]),

            ...\App\Filament\Support\DocumentFormFactory::linesSection(),

            \App\Filament\Support\DocumentFormFactory::totalsSection()
                ->visibleOn('edit')
                ->collapsible(),

            Forms\Components\Section::make('Observaciones')->schema([
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
                'secondary' => 'borrador', 'success' => 'confirmado', 'primary' => 'completado', 'warning' => 'parcial', 'danger' => 'anulado',
            ]),
        ])->filters([
            Tables\Filters\SelectFilter::make('estado'),
            Tables\Filters\Filter::make('bloqueados')->label('Solo bloqueados')->query(fn ($query) => $query->whereHas('documentosDerivados'))->toggle(),
        ])->actions([
            Tables\Actions\EditAction::make()->tooltip('Editar')->label('')->visible(fn($record) => $record->puedeEditarse()),
            Tables\Actions\Action::make('pdf')
                ->label('')
                ->tooltip('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn($record) => route('documentos.pdf', $record))
                ->openUrlInNewTab(),
            Tables\Actions\Action::make('convertir_albaran')
                ->label('')
                ->tooltip('Convertir a Albarán')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn($record) => in_array($record->estado, ['confirmado', 'parcial']))
                ->requiresConfirmation()
                ->action(function ($record) {
                    $albaran = $record->convertirA('albaran_compra');
                    Notification::make()->title('Albarán creado')->success()->body("Se ha creado el albarán {$albaran->numero}")->send();
                    return redirect()->route('filament.admin.resources.albaran-compras.edit', $albaran);
                }),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('agrupar_albaran')->label('Agrupar en Albarán')->icon('heroicon-o-document-duplicate')->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        try {
                            $service = new AgrupacionDocumentosService();
                            $albaran = $service->agruparPedidosCompraEnAlbaranCompra($records->pluck('id')->toArray());
                            Notification::make()->title('Albarán agrupado creado')->success()->body("Se ha creado el albarán {$albaran->numero}")->send();
                            return redirect()->route('filament.admin.resources.albaran-compras.edit', $albaran);
                        } catch (\Exception $e) {
                            Notification::make()->title('Error al agrupar')->danger()->body($e->getMessage())->send();
                        }
                    }),
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
            'index' => Pages\ListPedidosCompra::route('/'),
            'create' => Pages\CreatePedidoCompra::route('/create'),
            'edit' => Pages\EditPedidoCompra::route('/{record}/edit'),
        ];
    }
}
