<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('openInPOS')
                ->label('Abrir en POS')
                ->icon('heroicon-o-computer-desktop')
                ->url(fn () => $this->getResource()::getUrl('create', ['ticket_id' => $this->record->id]))
                ->color('primary')
                ->visible(fn () => $this->record->status === 'open'),
        ];
    }
}
