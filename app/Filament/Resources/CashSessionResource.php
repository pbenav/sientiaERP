<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashSessionResource\Pages;
use App\Filament\Resources\CashSessionResource\RelationManagers;
use App\Models\CashSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CashSessionResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Arqueos de Caja';

    protected static ?string $modelLabel = 'Arqueo';

    protected static ?string $pluralModelLabel = 'Arqueos';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Sesión')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Usuario')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('fecha_inicio')
                            ->label('Inicio')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('fecha_fin')
                            ->label('Cierre')
                            ->disabled(),
                        Forms\Components\TextInput::make('estado')
                            ->label('Estado')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'open' => 'Abierta',
                                'closed' => 'Cerrada',
                                default => $state,
                            })
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Totales')
                    ->schema([
                        Forms\Components\TextInput::make('fondo_apertura')
                            ->label('Fondo Apertura')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('total_tickets_efectivo')
                            ->label('Ventas Efectivo')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_tickets_tarjeta')
                            ->label('Ventas Tarjeta')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\TextInput::make('efectivo_final_real')
                            ->label('Efectivo Real (Cierre)')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('desfase')
                            ->label('Desfase')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Cierre')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'open',
                        'success' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Abierta',
                        'closed' => 'Cerrada',
                    }),
                Tables\Columns\TextColumn::make('fondo_apertura')
                    ->label('Fondo')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('total_tickets_efectivo')
                    ->label('Ventas Ef.')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('total_tickets_tarjeta')
                    ->label('Ventas Tarj.')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('desfase')
                    ->label('Desfase')
                    ->money('EUR')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'open' => 'Abierta',
                        'closed' => 'Cerrada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashSessions::route('/'),
            'create' => Pages\CreateCashSession::route('/create'),
            'view' => Pages\ViewCashSession::route('/{record}'),
            'edit' => Pages\EditCashSession::route('/{record}/edit'),
        ];
    }
}
