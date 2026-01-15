<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImpuestoResource\Pages;
use App\Filament\Resources\ImpuestoResource\RelationManagers;
use App\Models\Impuesto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImpuestoResource extends Resource
{
    protected static ?string $model = Impuesto::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Impuestos';

    protected static ?string $modelLabel = 'Impuesto';

    protected static ?string $pluralModelLabel = 'Impuestos';

    protected static ?string $navigationGroup = 'Gestión';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('activo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('valor')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListImpuestos::route('/'),
            'create' => Pages\CreateImpuesto::route('/create'),
            'edit' => Pages\EditImpuesto::route('/{record}/edit'),
        ];
    }
}
