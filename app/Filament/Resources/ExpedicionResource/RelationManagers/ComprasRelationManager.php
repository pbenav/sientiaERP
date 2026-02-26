<?php

namespace App\Filament\Resources\ExpedicionResource\RelationManagers;

use App\Models\Tercero;
use App\Models\TipoTercero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ComprasRelationManager extends RelationManager
{
    protected static string $relationship = 'compras';

    protected static ?string $title            = 'Compras en esta expedición';
    protected static ?string $modelLabel       = 'compra';
    protected static ?string $pluralModelLabel = 'compras';
    protected static ?string $recordTitleAttribute = 'id';

    // ── Autorización — siempre visible ───────────────────────────────────────
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function canCreate(): bool              { return true; }
    public function canEdit(Model $record): bool   { return true; }
    public function canDelete(Model $record): bool { return true; }

    // ── Formulario (modal) ────────────────────────────────────────────────────
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tercero_id')
                ->label('Proveedor')
                ->options(fn () => Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
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
                    $tercero = Tercero::create($data);
                    $tipo = TipoTercero::where('codigo', 'PRO')->first();
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
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('documento_path')
                ->label('Albarán (foto o PDF)')
                ->disk('public')
                ->directory('expediciones/documentos')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(10240)
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
        ])->columns(2);
    }

    // ── Tabla (lista compacta) ────────────────────────────────────────────────
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('fecha', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('importe')
                    ->label('Importe')
                    ->money('EUR', locale: 'es')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('pagado')
                    ->label('Pagado')
                    ->onColor('success')
                    ->offColor('danger')
                    ->alignCenter(),

                Tables\Columns\ToggleColumn::make('recogido')
                    ->label('Recogido')
                    ->onColor('success')
                    ->offColor('warning')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('documento_path')
                    ->label('Albarán')
                    ->icon(fn ($state) => $state ? 'heroicon-o-document' : null)
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir compra')
                    ->modalHeading('Nueva compra'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar compra'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('marcar_pagado')
                        ->label('Marcar como pagado')
                        ->icon('heroicon-o-banknotes')
                        ->action(fn ($records) => $records->each->update(['pagado' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('marcar_recogido')
                        ->label('Marcar como recogido')
                        ->icon('heroicon-o-truck')
                        ->action(fn ($records) => $records->each->update(['recogido' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
