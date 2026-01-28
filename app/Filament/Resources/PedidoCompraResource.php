<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoCompraResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
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
            Forms\Components\Section::make('Datos del Pedido de Compra')->schema([
                Forms\Components\TextInput::make('numero')->label('Número')->disabled()->dehydrated(false)->columnSpan(1),
                Forms\Components\Select::make('serie')->label('Serie')->options(\App\Models\BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))->default(fn() => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')->required()->columnSpan(1),
                Forms\Components\DatePicker::make('fecha')->label('Fecha')->default(now())->required()->columnSpan(1),
                Forms\Components\DatePicker::make('fecha_entrega')->label('Fecha de Recepción')->default(now()->addDays(7))->columnSpan(1),
                
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
                
                Forms\Components\Select::make('estado')->label('Estado')->options([
                    'borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'parcial' => 'Parcial',
                    'completado' => 'Completado', 'anulado' => 'Anulado',
                ])->default('borrador')->required()->columnSpan(2),
            ])->columns(3)->compact(),

            // SECCIÓN 3: PRODUCTOS
            Forms\Components\View::make('filament.components.document-lines')
                ->columnSpanFull(),

            // SECCIÓN 5: TOTALES (solo en edición)
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
                        ->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €')
                        ->extraAttributes(['class' => 'text-xl font-bold text-primary-600']),
                ])->columns(3)
                ->visibleOn('edit')
                ->collapsible(),

            // SECCIÓN 4: OBSERVACIONES
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
            Tables\Columns\TextColumn::make('total')->label('Total')->money('EUR')->sortable(),
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
            //
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
