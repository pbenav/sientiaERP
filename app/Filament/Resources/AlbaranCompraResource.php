<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlbaranCompraResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Models\Documento;
use App\Models\Tercero;
use App\Services\AgrupacionDocumentosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
        return $form->schema([
            \App\Filament\Support\DocumentFormFactory::terceroSection('Proveedor', 'PRO'),
            
            \App\Filament\Support\DocumentFormFactory::detailsSection('Datos del Albarán'),

            ...\App\Filament\Support\DocumentFormFactory::linesSection(),

            \App\Filament\Support\DocumentFormFactory::totalsSection()
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
                'secondary' => 'borrador', 'success' => 'confirmado', 'danger' => 'anulado',
            ]),
        ])->filters([
            Tables\Filters\SelectFilter::make('estado'),
            Tables\Filters\Filter::make('bloqueados')->label('Solo bloqueados')->query(fn ($query) => $query->whereHas('documentosDerivados'))->toggle(),
        ])->actions([
            Tables\Actions\EditAction::make()->tooltip('Editar')->label('')->visible(fn($record) => $record->puedeEditarse()),
            Tables\Actions\Action::make('generate_labels')
                ->label('')
                ->tooltip('Generar Etiquetas')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('label_format_id')
                        ->label('Formato de Etiqueta')
                        ->options(\App\Models\LabelFormat::where('activo', true)->pluck('nombre', 'id'))
                        ->required()
                        ->default(\App\Models\LabelFormat::where('activo', true)->first()?->id),
                ])
                ->action(function (Documento $record, array $data) {
                    $labelDoc = Documento::create([
                        'tipo' => 'etiqueta',
                        'estado' => 'borrador',
                        'user_id' => auth()->id(),
                        'fecha' => now(),
                        'label_format_id' => $data['label_format_id'],
                        'documento_origen_id' => $record->id,
                        'observaciones' => "Generado desde Albarán: " . ($record->numero ?? $record->referencia_proveedor),
                    ]);

                    foreach ($record->lineas as $linea) {
                        $labelDoc->lineas()->create([
                            'product_id' => $linea->product_id,
                            'codigo' => $linea->codigo ?: ($linea->product?->sku ?? $linea->product?->code ?? $linea->product?->barcode ?? null),
                            'descripcion' => $linea->descripcion,
                            'cantidad' => $linea->cantidad,
                            'unidad' => $linea->unidad,
                            'precio_unitario' => $linea->precio_unitario,
                        ]);
                    }

                    Notification::make()
                        ->title('Documento de etiquetas generado')
                        ->success()
                        ->send();
                })
                ->visible(fn (Documento $record) => !Documento::where('tipo', 'etiqueta')
                    ->where('documento_origen_id', $record->id)
                    ->exists()),
            Tables\Actions\Action::make('pdf')
                ->label('')
                ->tooltip('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn($record) => route('documentos.pdf', ['record' => $record->id]))
                ->openUrlInNewTab(),
            Tables\Actions\Action::make('print_labels')
                ->label('')
                ->tooltip('Imprimir Etiquetas')
                ->icon('heroicon-o-tag')
                ->color('success')
                ->url(function (Documento $record) {
                    $etiqueta = Documento::where('tipo', 'etiqueta')
                        ->where('documento_origen_id', $record->id)
                        ->first();
                    return $etiqueta ? route('etiquetas.pdf', ['record' => $etiqueta->id]) : '#';
                })
                ->visible(function (Documento $record) {
                    return Documento::where('tipo', 'etiqueta')
                        ->where('documento_origen_id', $record->id)
                        ->exists();
                })
                ->openUrlInNewTab(),
            Tables\Actions\Action::make('convertir_factura')
                ->label('')
                ->tooltip('Generar Factura')
                ->icon('heroicon-o-document-currency-euro')
                ->color('success')
                ->visible(fn($record) => $record->estado === 'confirmado')
                ->requiresConfirmation()
                ->action(function ($record) {
                    $factura = $record->convertirA('factura_compra');
                    Notification::make()->title('Factura creada')->success()->body("Se ha creado la factura {$factura->numero}")->send();
                    return redirect()->route('filament.admin.resources.factura-compras.edit', $factura);
                }),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('agrupar_factura')->label('Agrupar en Factura')->icon('heroicon-o-document-duplicate')->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        try {
                            $service = new AgrupacionDocumentosService();
                            $factura = $service->agruparAlbaranesCompraEnFacturaCompra($records->pluck('id')->toArray());
                            Notification::make()->title('Factura agrupada creada')->success()->body("Se ha creado la factura {$factura->numero}")->send();
                            return redirect()->route('filament.admin.resources.factura-compras.edit', $factura);
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
            'index' => Pages\ListAlbaranesCompra::route('/'),
            'create' => Pages\CreateAlbaranCompra::route('/create'),
            'edit' => Pages\EditAlbaranCompra::route('/{record}/edit'),
        ];
    }
}
