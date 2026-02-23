<?php

namespace App\Filament\Widgets;

use App\Models\Documento;
use App\Helpers\NumberFormatHelper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestDocumentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimos Documentos de Venta';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Documento::whereIn('tipo', ['factura', 'albaran', 'pedido'])
                    ->latest('fecha')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match($state) {
                        'factura' => 'Factura',
                        'albaran' => 'Albarán',
                        'pedido' => 'Pedido',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match($state) {
                        'factura' => 'success',
                        'albaran' => 'warning',
                        'pedido' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Cliente')
                    ->limit(30),
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => NumberFormatHelper::formatCurrency($state)),
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'borrador',
                        'success' => 'confirmado',
                        'primary' => 'completado',
                        'danger' => 'anulado',
                    ]),
            ]);
    }
}
