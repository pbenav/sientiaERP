<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoResource\Pages;
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
                                Forms\Components\TextInput::make('nombre_comercial')
                                    ->label('Nombre Comercial')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('nif_cif')
                                    ->label('NIF/CIF')
                                    ->required()
                                    ->maxLength(20),
                                
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(20),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $tercero = Tercero::create($data);
                                $tercero->tipos()->attach(\App\Models\TipoTercero::where('codigo', 'CLI')->first());
                                return $tercero->id;
                            }),
                    ])->columns(1),

                // SECCIÓN 2: DATOS DEL DOCUMENTO
                Forms\Components\Section::make('Datos del Pedido')
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
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')
                                    ->required()
                                    ->maxLength(100),
                                
                                Forms\Components\Select::make('tipo')
                                    ->options([
                                        'contado' => 'Contado',
                                        'transferencia' => 'Transferencia',
                                        'tarjeta' => 'Tarjeta',
                                    ])
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return FormaPago::create([
                                    'codigo' => \Str::slug($data['nombre']),
                                    'nombre' => $data['nombre'],
                                    'tipo' => $data['tipo'],
                                    'tramos' => [['dias' => 0, 'porcentaje' => 100]],
                                    'activo' => true,
                                ])->id;
                            }),
                        
                        Forms\Components\Placeholder::make('subtotal_display')->label('Subtotal')->content(fn($record) => $record ? number_format($record->subtotal, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('iva_display')->label('IVA')->content(fn($record) => $record ? number_format($record->iva, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('total_display')->label('TOTAL')->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                    ])->columns(6)->compact(),

                // SECCIÓN 3: LÍNEAS DE PRODUCTOS
                Forms\Components\Repeater::make('lineas')
                    ->label('Líneas del Pedido')
                    ->relationship('lineas')
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema())
                    ->columns(8)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->reorderable()
                    ->addActionLabel('+ Añadir línea')
                    ->collapsible()
                    ->cloneable(),


                // SECCIÓN 4: OBSERVACIONES
                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones (visibles en el documento)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->visibleOn('edit')
                    ->collapsible(),

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
                            ->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €'),
                    ])->columns(3)
                    ->visibleOn('edit')
                    ->collapsible(),
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
                    ->money('EUR')
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
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->puedeEditarse())
                    ->tooltip(fn($record) => !$record->puedeEditarse() ? $record->getMensajeBloqueo() : null),
                
                // Ver documento bloqueante
                Tables\Actions\Action::make('ver_bloqueante')
                    ->label('Ver bloqueante')
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
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                
                // Convertir a Albarán
                Tables\Actions\Action::make('convertir_albaran')
                    ->label('Convertir a Albarán')
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
                        
                        return redirect()->route('filament.admin.resources.albarans.edit', $albaran);
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
                                
                                return redirect()->route('filament.admin.resources.albarans.edit', $albaran);
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
        return [];
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
