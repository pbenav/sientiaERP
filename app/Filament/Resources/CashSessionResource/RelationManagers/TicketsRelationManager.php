<?php

namespace App\Filament\Resources\CashSessionResource\RelationManagers;

use App\Filament\Resources\TicketResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Ventas del Arqueo';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    public function form(Form $form): Form
    {
        return TicketResource::form($form);
    }

    public function table(Table $table): Table
    {
        return TicketResource::table($table)
            ->recordTitleAttribute('numero')
            ->headerActions([
                // Las ventas se crean desde el POS, no desde aquí habitualmente,
                // pero dejamos la navegación si fuera necesaria
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
