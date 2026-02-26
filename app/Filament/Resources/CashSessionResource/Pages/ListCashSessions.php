<?php

namespace App\Filament\Resources\CashSessionResource\Pages;

use App\Filament\Resources\CashSessionResource;
use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashSessions extends ListRecords
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
                ->visible(fn () => \App\Models\CashSession::where('user_id', auth()->id())->where('estado', 'open')->exists()),
            Actions\CreateAction::make()
                ->label('Nuevo Arqueo'),
        ];
    }
}
