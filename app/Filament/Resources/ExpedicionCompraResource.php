<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpedicionCompraResource\Pages;
use App\Filament\Resources\ExpedicionCompraResource\Widgets\ExpedicionStatsWidget;
use App\Models\ExpedicionCompra;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ExpedicionCompraResource extends Resource
{
    protected static ?string $model = ExpedicionCompra::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Expedición de Compras';
    protected static ?string $navigationGroup = 'Compras';
    protected static ?int    $navigationSort  = 99;
    protected static ?string $modelLabel      = 'expedición';
    protected static ?string $pluralModelLabel = 'expediciones de compra';

    // ── Formulario ────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la expedición')
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),

                    Forms\Components\TextInput::make('proveedor')
                        ->label('Proveedor')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('importe')
                        ->label('Importe (€)')
                        ->numeric()
                        ->step(0.01)
                        ->required()
                        ->suffix('€'),

                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->columnSpanFull()
                        ->rows(3),
                ]),

            Forms\Components\Section::make('Estado')
                ->columns(2)
                ->schema([
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

            Forms\Components\Section::make('Documento (albarán)')
                ->schema([
                    Forms\Components\FileUpload::make('documento_path')
                        ->label('Subir albarán (imagen o PDF)')
                        ->disk('public')
                        ->directory('expediciones/documentos')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240)
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ── Tabla ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->where('archivado', false))
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('alerta')
                    ->label('⚠️')
                    ->state(fn (ExpedicionCompra $r) => $r->tieneAlerta())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip('Pagado pero no recogido'),

                Tables\Columns\TextColumn::make('proveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('importe')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(40)
                    ->toggleable(),

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
                    ->state(fn (ExpedicionCompra $r) => !empty($r->documento_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('')
                    ->trueColor('info')
                    ->tooltip('Tiene documento adjunto'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('pagado')
                    ->label('Pagado'),

                Tables\Filters\TernaryFilter::make('recogido')
                    ->label('Recogido'),

                Tables\Filters\Filter::make('con_alerta')
                    ->label('⚠️ Pagado sin recoger')
                    ->query(fn (Builder $q) => $q->where('pagado', true)->where('recogido', false)),
            ])
            ->actions([
                // ── Importar al OCR ──────────────────────────────────────────
                Action::make('importar_albaran')
                    ->label('Importar albarán')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->visible(fn (ExpedicionCompra $r) => !empty($r->documento_path))
                    ->action(function (ExpedicionCompra $record) {
                        // Guardamos el path en sesión para que OcrImport lo lea
                        session(['expedicion_documento_path' => $record->documento_path]);
                    })
                    ->url(fn (ExpedicionCompra $r) => route('filament.admin.pages.ocr-import') . '?from_expedicion=' . $r->id)
                    ->openUrlInNewTab(false)
                    ->tooltip('Enviar documento al importador OCR'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                // ── Resetear expedición ──────────────────────────────────────
                Action::make('resetear')
                    ->label('Resetear expedición')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Resetear expedición activa')
                    ->modalDescription('Se archivarán todos los registros actuales. Podrás consultarlos en el histórico. ¿Continuar?')
                    ->modalSubmitActionLabel('Sí, archivar y resetear')
                    ->action(function () {
                        $archivados = ExpedicionCompra::where('archivado', false)->update(['archivado' => true]);

                        Notification::make()
                            ->title('Expedición archivada')
                            ->body("{$archivados} registros archivados correctamente.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('marcar_pagado')
                        ->label('Marcar como pagado')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['pagado' => true]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('marcar_recogido')
                        ->label('Marcar como recogido')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn ($records) => $records->each->update(['recogido' => true]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    // ── Widgets ───────────────────────────────────────────────────────────────

    public static function getWidgets(): array
    {
        return [
            ExpedicionStatsWidget::class,
        ];
    }

    // ── Páginas ───────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpedicionesCompra::route('/'),
            'create' => Pages\CreateExpedicionCompra::route('/create'),
            'edit'   => Pages\EditExpedicionCompra::route('/{record}/edit'),
        ];
    }
}
