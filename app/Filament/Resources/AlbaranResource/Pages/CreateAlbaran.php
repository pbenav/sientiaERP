<?php

namespace App\Filament\Resources\AlbaranResource\Pages;

use App\Filament\Resources\AlbaranResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlbaran extends CreateRecord
{
    protected static string $resource = AlbaranResource::class;

    public function mount(): void
    {
        $data = \App\Models\Documento::getDefaultsFor('albaran');

        $record = static::getModel()::create($data);

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
