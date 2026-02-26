<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpedicionResource\Pages;
use App\Filament\Resources\ExpedicionResource\RelationManagers\ComprasRelationManager;
use App\Filament\Resources\ExpedicionResource\Widgets\ExpedicionTotalesWidget;
use App\Models\Expedicion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpedicionResource extends Resource
{
    protected static ?string $model = Expedicion::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Expedición de Compras';
    protected static ?string $navigationGroup = 'Compras';
    protected static ?int    $navigationSort  = 99;
    protected static ?string $modelLabel      = 'expedición';
    protected static ?string $pluralModelLabel = 'expediciones de compra';

    // ── Formulario de cabecera de la expedición ───────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            // ── Cabecera de la expedición ─────────────────────────────────────
            Forms\Components\Section::make('Datos de la expedición')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre / Feria')
                        ->placeholder('ej: FITUR 2026')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),

                    Forms\Components\TextInput::make('lugar')
                        ->label('Lugar')
                        ->placeholder('ej: Madrid')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('descripcion')
                        ->label('Descripción / Notas')
                        ->rows(2)
                        ->columnSpan(2),
                ]),

            // ── Totales — solo en edición ─────────────────────────────────────
            Forms\Components\Section::make('Totales de la expedición')
                ->columns(3)
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Placeholder::make('total_importe')
                        ->label('💶 Total compras')
                        ->content(function ($record) {
                            if (!$record) return '—';
                            return number_format($record->compras()->sum('importe'), 2, ',', '.') . ' €';
                        }),

                    Forms\Components\Placeholder::make('pendientes_recogida')
                        ->label('🚚 Pendientes de recoger')
                        ->content(function ($record) {
                            if (!$record) return '—';
                            $n = $record->compras()->where('pagado', true)->where('recogido', false)->count();
                            return $n > 0 ? "⚠️ {$n} compra(s)" : '✅ Todo recogido';
                        }),

                    Forms\Components\Placeholder::make('sin_pagar')
                        ->label('💳 Sin pagar')
                        ->content(function ($record) {
                            if (!$record) return '—';
                            $n = $record->compras()->where('pagado', false)->count();
                            return $n > 0 ? "⚠️ {$n} compra(s)" : '✅ Todo pagado';
                        }),
                ]),

            // ── Compras de la expedición ─────────────────────────────────────
            Forms\Components\Section::make('Compras en esta expedición')
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Repeater::make('compras')
                        ->relationship('compras')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->addActionLabel('➕ Añadir compra')
                        ->columns(2)
                        ->schema([
                            // Proveedor del maestro
                            Forms\Components\Select::make('tercero_id')
                                ->label('Proveedor')
                                ->options(fn () => \App\Models\Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(2)
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('nombre_comercial')
                                        ->label('Nombre Comercial')->required()->maxLength(255),
                                    Forms\Components\TextInput::make('nif_cif')
                                        ->label('NIF/CIF')->maxLength(20),
                                    Forms\Components\TextInput::make('email')
                                        ->label('Email')->email()->maxLength(255),
                                    Forms\Components\TextInput::make('telefono')
                                        ->label('Teléfono')->tel()->maxLength(20),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $tercero = \App\Models\Tercero::create($data);
                                    $tipo = \App\Models\TipoTercero::where('codigo', 'PRO')->first();
                                    if ($tipo) $tercero->tipos()->attach($tipo);
                                    return $tercero->id;
                                }),

                            Forms\Components\TextInput::make('importe')
                                ->label('Importe (€)')
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->suffix('€'),

                            Forms\Components\DatePicker::make('fecha')
                                ->label('Fecha')
                                ->default(now())
                                ->required(),

                            Forms\Components\Toggle::make('pagado')
                                ->label('Pagado')
                                ->default(false)
                                ->onColor('success')
                                ->offColor('danger'),

                            Forms\Components\Toggle::make('recogido')
                                ->label('Mercancía recogida')
                                ->default(false)
                                ->onColor('success')
                                ->offColor('warning'),

                            Forms\Components\Textarea::make('observaciones')
                                ->label('Observaciones')
                                ->rows(2)
                                ->columnSpan(2),

                            Forms\Components\FileUpload::make('documento_path')
                                ->label('Albarán')
                                ->disk('public')
                                ->directory('expediciones/documentos')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->maxSize(10240)
                                ->downloadable()
                                ->openable()
                                ->columnSpan(2),
                        ]),
                ]),
        ]);
    }

    // ── Listado de expediciones ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Expedición / Feria')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lugar')
                    ->label('Lugar')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('compras_count')
                    ->label('Compras')
                    ->counts('compras')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_importe')
                    ->label('Total')
                    ->state(fn ($record) => number_format($record->totalImporte(), 2, ',', '.') . ' €')
                    ->alignRight()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('pendientes_recogida')
                    ->label('⚠️ Sin recoger')
                    ->state(fn ($record) => $record->pendientesRecogida())
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record) => $record->pendientesRecogida() > 0 ? 'danger' : 'success'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Abrir'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ── RelationManagers ──────────────────────────────────────────────────────

    public static function getRelationManagers(): array
    {
        return [
            ComprasRelationManager::class,
        ];
    }

    // ── Widgets ───────────────────────────────────────────────────────────────

    public static function getWidgets(): array
    {
        return [
            ExpedicionTotalesWidget::class,
        ];
    }

    // ── Páginas ───────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpediciones::route('/'),
            'create' => Pages\CreateExpedicion::route('/create'),
            'edit'   => Pages\EditExpedicion::route('/{record}/edit'),
        ];
    }
}
