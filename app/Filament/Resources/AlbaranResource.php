<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlbaranResource\Pages;
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

class AlbaranResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Albaranes';

    protected static ?string $modelLabel = 'Albarán';

    protected static ?string $pluralModelLabel = 'Albaranes';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationGroup = 'Ventas';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'albaran');
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

                // SECCIÓN 2: DATOS DEL ALBARÁN
                Forms\Components\Section::make('Datos del Albarán')
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
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'confirmado' => 'Confirmado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                        
                        Forms\Components\Placeholder::make('subtotal_display')->label('Subtotal')->content(fn($record) => $record ? number_format($record->subtotal, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('iva_display')->label('IVA')->content(fn($record) => $record ? number_format($record->iva, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                        Forms\Components\Placeholder::make('total_display')->label('TOTAL')->content(fn($record) => $record ? number_format($record->total, 2, ',', '.') . ' €' : '0,00 €')->visibleOn('edit'),
                    ])->columns(6)->compact(),

                Forms\Components\Repeater::make('lineas')
                    ->label('Líneas del Albarán')
                    ->relationship('lineas')
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema())
                    ->columns(8)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->reorderable()
                    ->addActionLabel('+ Añadir línea')
                    ->collapsible()
                    ->cloneable(),


                Forms\Components\Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),
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
                        'danger' => 'anulado',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado'),
                Tables\Filters\Filter::make('bloqueados')
                    ->label('Solo bloqueados')
                    ->query(fn ($query) => $query->whereHas('documentosDerivados'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->puedeEditarse())
                    ->tooltip(fn($record) => !$record->puedeEditarse() ? $record->getMensajeBloqueo() : null),
                
                Tables\Actions\Action::make('ver_bloqueante')
                    ->label('Ver bloqueante')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn($record) => !$record->puedeEditarse() && $record->getDocumentosBloqueantes()->isNotEmpty())
                    ->url(function ($record) {
                        $bloqueante = $record->getDocumentosBloqueantes()->first();
                        return $bloqueante->tipo === 'pedido' 
                            ? PedidoResource::getUrl('edit', ['record' => $bloqueante])
                            : null;
                    })
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('convertir_factura')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-currency-euro')
                    ->color('success')
                    ->visible(fn($record) => $record->estado === 'confirmado')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $factura = $record->convertirA('factura');
                        
                        Notification::make()
                            ->title('Factura creada')
                            ->success()
                            ->body("Se ha creado la factura {$factura->numero}")
                            ->send();
                        
                        return redirect()->route('filament.admin.resources.facturas.edit', $factura);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('agrupar_factura')
                        ->label('Agrupar en Factura')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            try {
                                $service = new AgrupacionDocumentosService();
                                $factura = $service->agruparAlbaranesEnFactura($records->pluck('id')->toArray());
                                
                                Notification::make()
                                    ->title('Factura agrupada creada')
                                    ->success()
                                    ->body("Se ha creado la factura {$factura->numero} con {$records->count()} albaranes")
                                    ->send();
                                
                                return redirect()->route('filament.admin.resources.facturas.edit', $factura);
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
            'index' => Pages\ListAlbaranes::route('/'),
            'create' => Pages\CreateAlbaran::route('/create'),
            'edit' => Pages\EditAlbaran::route('/{record}/edit'),
        ];
    }
}
