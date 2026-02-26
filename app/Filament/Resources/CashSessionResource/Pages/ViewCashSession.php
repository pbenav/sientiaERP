<?php

namespace App\Filament\Resources\CashSessionResource\Pages;

use App\Filament\Resources\CashSessionResource;
use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCashSession extends ViewRecord
{
    protected static string $resource = CashSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('abrir_tpv')
                ->label('Ir al TPV')
                ->icon('heroicon-o-computer-desktop')
                ->color('warning')
                ->url(fn () => TicketResource::getUrl('create'))
                ->visible(fn ($record) => $record->estado === 'open'),
            Actions\EditAction::make(),
        ];
    }
}
