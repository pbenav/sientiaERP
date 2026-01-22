<?php

namespace App\Filament\Resources\AlbaranResource\Pages;

use App\Filament\Resources\AlbaranResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlbaran extends EditRecord
{
    protected static string $resource = AlbaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estado === 'borrador')
                ->action(fn() => $this->record->confirmar()),
            
            Actions\DeleteAction::make(),
        ];
    }

    protected $listeners = ['refresh-document-totals' => '$refresh'];

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->recalcularTotales();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
