<?php

namespace App\Filament\Resources\ReciboResource\Pages;

use App\Filament\Resources\ReciboResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecibo extends EditRecord
{
    protected static string $resource = ReciboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        parent::save($shouldRedirect, $shouldSendSavedNotification);
        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


}
