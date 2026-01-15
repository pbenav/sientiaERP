<?php

namespace App\Filament\Resources\BillingSerieResource\Pages;

use App\Filament\Resources\BillingSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillingSerie extends EditRecord
{
    protected static string $resource = BillingSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
