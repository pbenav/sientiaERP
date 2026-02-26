<?php

namespace App\Filament\Resources\ExpedicionResource\RelationManagers;

use App\Models\Tercero;
use App\Models\TipoTercero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class ComprasRelationManager extends RelationManager
{
    protected static string $relationship = 'compras';
    protected static ?string $title = 'Compras en esta expedición';
    protected static ?string $modelLabel = 'compra';
    protected static ?string $pluralModelLabel = 'compras';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([

                // ── Proveedor ─────────────────────────────────────────────────
                Forms\Components\Select::make('tercero_id')
                    ->label('Proveedor')
                    ->options(fn () => Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(2)
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre_comercial')
                            ->label('Nombre Comercial')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nif_cif')
                            ->label('NIF/CIF')
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
                        $tipo = TipoTercero::where('codigo', 'PRO')->first();
                        if ($tipo) {
                            $tercero->tipos()->attach($tipo);
                        }
                        return $tercero->id;
                    }),

                // ── Datos de la compra ─────────────────────────────────────────
                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->default(fn () => $this->getOwnerRecord()->fecha ?? now())
                    ->required(),

                Forms\Components\TextInput::make('importe')
                    ->label('Importe (€)')
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->suffix('€'),

                Forms\Components\Textarea::make('observaciones')
                    ->label('Observaciones')
                    ->columnSpan(2)
                    ->rows(2),

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
            ]),

            // ── Albarán ──────────────────────────────────────────────────────
            Forms\Components\FileUpload::make('documento_path')
                ->label('Albarán (imagen o PDF)')
                ->disk('public')
                ->directory('expediciones/documentos')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(10240)
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('alerta')
                    ->label('⚠️')
                    ->state(fn ($record) => $record->pagado && !$record->recogido)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip('Pagado pero no recogido'),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Proveedor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('importe')
                    ->label('Importe')
                    ->money('EUR')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\IconColumn::make('pagado')
                    ->label('Pagado')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('recogido')
                    ->label('Recogido')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\IconColumn::make('documento_path')
                    ->label('Doc.')
                    ->state(fn ($record) => !empty($record->documento_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('')
                    ->trueColor('info'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir compra'),
            ])
            ->actions([
                Action::make('importar_albaran')
                    ->label('Importar al OCR')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->visible(fn ($record) => !empty($record->documento_path))
                    ->url(fn ($record) => route('filament.admin.pages.ocr-import') . '?from_expedicion=' . $record->id)
                    ->tooltip('Enviar albarán al importador OCR'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('marcar_pagado')
                        ->label('Marcar pagado')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['pagado' => true]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('marcar_recogido')
                        ->label('Marcar recogido')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn ($records) => $records->each->update(['recogido' => true]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
