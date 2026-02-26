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

            // ── Totales — solo visible al editar ─────────────────────────────
            Forms\Components\Section::make('Totales de la expedición')
                ->columns(3)
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Placeholder::make('total_importe')
                        ->label('💶 Total compras')
                        ->content(function ($record) {
                            if (!$record) return '—';
                            $total = $record->compras()->sum('importe');
                            return number_format($total, 2, ',', '.') . ' €';
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
