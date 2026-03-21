<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturaResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Filament\Support\HasRoleAccess;
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
    use HasRoleAccess;

    protected static string $viewPermission   = 'ventas.view';
    protected static string $createPermission = 'ventas.create';
    protected static string $editPermission   = 'ventas.edit';
    protected static string $deletePermission = 'ventas.delete';

    protected static ?string $model = Documento::class;
    protected static ?string $slug = 'facturas';

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
                \App\Filament\Support\DocumentFormFactory::terceroSection('Cliente', 'CLI')
                    ->disabled(fn ($record) => $record && $record->estado !== 'borrador'),
                
                \App\Filament\Support\DocumentFormFactory::detailsSection('Datos de la Factura', [
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
                    
                    Forms\Components\Select::make('porcentaje_irpf')
                        ->label('IRPF %')
                        ->options(\App\Models\Impuesto::where('tipo', 'irpf')->where('activo', true)->pluck('nombre', 'valor'))
                        ->default(0)
                        ->live()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->required(),
                            Forms\Components\TextInput::make('valor')->numeric()->required()->suffix('%'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return \App\Models\Impuesto::create([...$data, 'tipo' => 'irpf', 'activo' => true])->valor;
                        })
                        ->afterStateUpdated(fn ($record) => $record?->recalcularTotales()),

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
                ])->disabled(fn ($record) => $record && $record->estado !== 'borrador'),

                ...\App\Filament\Support\DocumentFormFactory::linesSection(),

                \App\Filament\Support\DocumentFormFactory::totalsSection()
                    ->visibleOn('edit')
                    ->collapsible(),

                        Forms\Components\Section::make('Observaciones')
                            ->schema([
                                Forms\Components\Textarea::make('observaciones')
                                    ->label('Observaciones (visibles en el documento)')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Section::make('Veri*Factu (Digital Signature)')
                            ->icon('heroicon-o-shield-check')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('verifactu_status')
                                            ->label('Estado AEAT')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('verifactu_aeat_id')
                                            ->label('ID Traza AEAT')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('verifactu_huella')
                                            ->label('Huella Digital (Hash)')
                                            ->disabled()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('verifactu_huella_anterior')
                                            ->label('Huella Anterior (Chain)')
                                            ->disabled()
                                            ->columnSpan(2),
                                    ])
                            ])
                            ->visible(fn($record) => $record && !empty($record->verifactu_huella)),
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
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'primary' => 'cobrado',
                        'danger' => 'anulado',
                    ]),
                Tables\Columns\IconColumn::make('verifactu_status')
                    ->label('VF')
                    ->options([
                        'heroicon-s-check-circle' => 'accepted',
                        'heroicon-s-x-circle' => 'error',
                        'heroicon-s-clock' => 'pending',
                    ])
                    ->colors([
                        'success' => 'accepted',
                        'danger' => 'error',
                        'warning' => 'pending',
                    ])
                    ->tooltip(fn($record) => $record->verifactu_aeat_id ? "AEAT ID: {$record->verifactu_aeat_id}" : "Veri*Factu: {$record->verifactu_status}")
                    ->visible(fn() => \App\Models\Setting::get('verifactu_active', false)),
                
                Tables\Columns\TextColumn::make('recibos_count')
                    ->label('Recibos')
                    ->counts('documentosDerivados', fn($query) => $query->where('tipo', 'recibo'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                
                Tables\Actions\DeleteAction::make()->tooltip('Eliminar')->label('')
                    ->visible(fn($record) => $record->puedeEliminarse()),
                
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn($record) => route('documentos.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('facturae')
                    ->label('')
                    ->tooltip('Descargar Facturae (XML)')
                    ->icon('heroicon-o-code-bracket')
                    ->color('warning')
                    ->url(fn($record) => route('facturae.download', $record))
                    ->visible(fn($record) => \App\Models\Setting::get('facturae_active', false) && $record->estado === 'confirmado' && !empty($record->numero)),
                
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
                    Tables\Actions\BulkAction::make('borrar_en_cadena')
                        ->label('Borrar en cadena')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Esta acción borrará las facturas seleccionadas Y TODA su cadena de origen (albaranes, pedidos, etc.) de forma recursiva. IMPORTANTE: Solo se eliminarán los documentos que no estén bloqueados oficialmente (como facturas con número asignado).')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->borrarEnCadena()) {
                                    $count++;
                                }
                            }
                            \Filament\Notifications\Notification::make()->title("Se han eliminado $count cadenas de documentos")->success()->send();
                        }),
                    
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
            'index' => Pages\ListFacturas::route('/'),
            'create' => Pages\CreateFactura::route('/create'),
            'edit' => Pages\EditFactura::route('/{record}/edit'),
        ];
    }
}
