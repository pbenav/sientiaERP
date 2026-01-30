<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresupuestoResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;

class PresupuestoResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Presupuestos';

    protected static ?string $modelLabel = 'Presupuesto';

    protected static ?string $pluralModelLabel = 'Presupuestos';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationGroup = 'Ventas';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'presupuesto');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Presupuesto')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente'),
                        
                        Forms\Components\Select::make('serie')
                            ->label('Serie')
                            ->options(\App\Models\BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))
                            ->default(fn() => \App\Models\BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('codigo')->label('Código de Serie')->required()->maxLength(10),
                                Forms\Components\TextInput::make('nombre')->label('Nombre')->required(),
                                Forms\Components\Toggle::make('devenga_iva')->label('Devenga IVA')->default(true),
                            ])
                            ->createOptionUsing(fn (array $data) => \App\Models\BillingSerie::create($data)->codigo),
                        
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\DatePicker::make('fecha_validez')
                            ->label('Válido hasta')
                            ->default(function() {
                                $diasValidez = (int)\App\Models\Setting::get('presupuesto_validez_dias', 5);
                                return now()->addDays($diasValidez);
                            })
                            ->required(),
                        
                        Forms\Components\Select::make('tercero_id')
                            ->label('Cliente')
                            ->options(fn() => \App\Models\Tercero::clientes()->pluck('nombre_comercial', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre_comercial')
                                    ->required(),
                                Forms\Components\TextInput::make('nif_cif')
                                    ->required(),
                            ]),
                        
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
                        
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'confirmado' => 'Confirmado',
                                'anulado' => 'Anulado',
                            ])
                            ->default('borrador')
                            ->required(),
                    ])->columns(3),

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
                        Forms\Components\Placeholder::make('totales_calculados')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get) {
                                $lineas = $get('lineas') ?? [];
                                // Determine if Recargo applies (logic usually depends on Tercero, here simplified or fetched)
                                $terceroId = $get('tercero_id');
                                $tieneRecargo = false;
                                if ($terceroId) {
                                    $tercero = \App\Models\Tercero::find($terceroId);
                                    $tieneRecargo = $tercero?->recargo_equivalencia ?? false;
                                }
                                
                                $breakdown = \App\Services\DocumentCalculator::calculate($lineas, $tieneRecargo);
                                
                                return view('filament.components.tax-breakdown-live', [
                                    'breakdown' => $breakdown, 
                                    'tieneRecargo' => $tieneRecargo
                                ]);
                            })
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
                        
                        Forms\Components\Textarea::make('observaciones_internas')
                            ->label('Observaciones internas (no visibles)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'danger' => 'anulado',
                    ]),
                
                Tables\Columns\TextColumn::make('fecha_validez')
                    ->label('Válido hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado' => 'Anulado',
                    ]),
                
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde'),
                        Forms\Components\DatePicker::make('hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q, $date) => $q->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'], fn($q, $date) => $q->whereDate('fecha', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('convertir_pedido')
                    ->label('')
                    ->tooltip('Convertir a Pedido')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->visible(fn($record) => $record->estado === 'confirmado')
                    ->action(function ($record) {
                        $pedido = $record->convertirA('pedido');
                        return redirect()->route('filament.admin.resources.pedidos.edit', $pedido);
                    }),
                Tables\Actions\DeleteAction::make()->tooltip('Borrar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPresupuestos::route('/'),
            'create' => Pages\CreatePresupuesto::route('/create'),
            'edit' => Pages\EditPresupuesto::route('/{record}/edit'),
        ];
    }
}
