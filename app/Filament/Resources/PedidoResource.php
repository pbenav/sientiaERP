<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoResource\Pages;
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

class PedidoResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationGroup = 'Ventas';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'pedido');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                \App\Filament\Support\DocumentFormFactory::terceroSection('Cliente', 'CLI'),

                \App\Filament\Support\DocumentFormFactory::detailsSection('Datos del Pedido', [
                    Forms\Components\DatePicker::make('fecha_entrega')
                        ->label('Fecha de Entrega')
                        ->default(now()->addDays(7)),
                    
                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options([
                            'borrador' => 'Borrador',
                            'confirmado' => 'Confirmado',
                            'parcial' => 'Parcial',
                            'completado' => 'Completado',
                            'anulado' => 'Anulado',
                        ])
                        ->default('borrador')
                        ->required(),
                    
                    Forms\Components\Select::make('forma_pago_id')
                        ->label('Forma de Pago')
                        ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                        ->searchable()
                        ->preload()
                        ->default(fn() => \App\Models\FormaPago::activas()->first()?->id ?? 1)
                        ->required()
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
        return $table
            ->columns([
                // Indicador de bloqueo
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
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'primary' => 'completado',
                        'warning' => 'parcial',
                        'danger' => 'anulado',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'parcial' => 'Parcial',
                        'completado' => 'Completado',
                        'anulado' => 'Anulado',
                    ]),
                
                Tables\Filters\Filter::make('bloqueados')
                    ->label('Solo bloqueados')
                    ->query(fn ($query) => $query->whereHas('documentosDerivados'))
                    ->toggle(),
            ])
            ->actions([
                // Editar (solo si puede editarse)
                Tables\Actions\EditAction::make()->tooltip('Editar')->label('')
                    ->visible(fn($record) => $record->puedeEditarse())
                    ->tooltip(fn($record) => !$record->puedeEditarse() ? $record->getMensajeBloqueo() : null),
                
                // Ver documento bloqueante
                // Ver documento bloqueante
                Tables\Actions\Action::make('ver_bloqueante')
                    ->label('')
                    ->tooltip('Ver bloqueante')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn($record) => !$record->puedeEditarse() && $record->getDocumentosBloqueantes()->isNotEmpty())
                    ->url(function ($record) {
                        $bloqueante = $record->getDocumentosBloqueantes()->first();
                        $resourceMap = [
                            'pedido' => PedidoResource::class,
                            'albaran' => AlbaranResource::class,
                            'factura' => FacturaResource::class,
                        ];
                        
                        $resourceClass = $resourceMap[$bloqueante->tipo] ?? null;
                        return $resourceClass ? $resourceClass::getUrl('edit', ['record' => $bloqueante]) : null;
                    })
                    ->openUrlInNewTab(),
                
                // PDF
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                
                // Convertir a Albarán
                Tables\Actions\Action::make('convertir_albaran')
                    ->label('')
                    ->tooltip('Convertir a Albarán')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn($record) => in_array($record->estado, ['confirmado', 'parcial']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $albaran = $record->convertirA('albaran');
                        
                        Notification::make()
                            ->title('Albarán creado')
                            ->success()
                            ->body("Se ha creado el albarán {$albaran->numero}")
                            ->send();
                        
                        return redirect()->to(AlbaranResource::getUrl('edit', ['record' => $albaran]));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Agrupar en Albarán
                    Tables\Actions\BulkAction::make('agrupar_albaran')
                        ->label('Agrupar en Albarán')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Agrupar Pedidos en Albarán')
                        ->modalDescription('¿Deseas agrupar los pedidos seleccionados en un único albarán?')
                        ->action(function ($records) {
                            try {
                                $service = new AgrupacionDocumentosService();
                                $albaran = $service->agruparPedidosEnAlbaran($records->pluck('id')->toArray());
                                
                                Notification::make()
                                    ->title('Albarán agrupado creado')
                                    ->success()
                                    ->body("Se ha creado el albarán {$albaran->numero} con {$records->count()} pedidos")
                                    ->send();
                                
                                return redirect()->to(AlbaranResource::getUrl('edit', ['record' => $albaran]));
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error al agrupar')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                    
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
            'index' => Pages\ListPedidos::route('/'),
            'create' => Pages\CreatePedido::route('/create'),
            'edit' => Pages\EditPedido::route('/{record}/edit'),
        ];
    }
}
