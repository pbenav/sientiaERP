<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresupuestoCompraResource\Pages;
use App\Filament\RelationManagers\LineasRelationManager;
use App\Filament\Support\HasRoleAccess;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PresupuestoCompraResource extends Resource
{
    use HasRoleAccess;

    protected static string $viewPermission   = 'compras.view';
    protected static string $createPermission = 'compras.create';
    protected static string $editPermission   = 'compras.edit';
    protected static string $deletePermission = 'compras.delete';

    protected static ?string $model = Documento::class;
    protected static ?string $slug = 'presupuesto-compras';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Presupuestos de Compra';
    protected static ?string $modelLabel = 'Presupuesto de Compra';
    protected static ?string $pluralModelLabel = 'Presupuestos de Compra';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationGroup = 'Compras';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'presupuesto_compra');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                \App\Filament\Support\DocumentFormFactory::terceroSection('Proveedor', 'PRO'),

                \App\Filament\Support\DocumentFormFactory::detailsSection('Datos del Presupuesto', [
                    Forms\Components\DatePicker::make('fecha_validez')
                        ->label('Válido hasta')
                        ->default(function() {
                            $diasValidez = (int)\App\Models\Setting::get('presupuesto_validez_dias', 5);
                            return now()->addDays($diasValidez);
                        })
                        ->required(),
                    
                    Forms\Components\Select::make('forma_pago_id')
                        ->label('Forma de Pago')
                        ->relationship('formaPago', 'nombre', fn($query) => $query->activas())
                        ->searchable()
                        ->preload()
                        ->default(fn() => \App\Models\Setting::get('default_forma_pago_id', 1))
                        ->required(),
                ]),

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
                    ->label('Proveedor')
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\Action::make('convertir_pedido')
                    ->label('')
                    ->tooltip('Convertir a Pedido')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->visible(fn($record) => $record->estado === 'confirmado')
                    ->action(function ($record) {
                        $pedido = $record->convertirA('pedido_compra');
                        return redirect()->route('filament.admin.resources.pedido-compras.edit', $pedido);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('borrar_en_cadena')
                        ->label('Borrar en cadena')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Esta acción borrará los presupuestos seleccionados. IMPORTANTE: Solo se eliminarán los documentos que no estén bloqueados oficialmente.')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->borrarEnCadena()) {
                                    $count++;
                                }
                            }
                            \Filament\Notifications\Notification::make()->title("Se han eliminado $count documentos")->success()->send();
                        }),
                    
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPresupuestosCompra::route('/'),
            'create' => Pages\CreatePresupuestoCompra::route('/create'),
            'edit' => Pages\EditPresupuestoCompra::route('/{record}/edit'),
        ];
    }
}
