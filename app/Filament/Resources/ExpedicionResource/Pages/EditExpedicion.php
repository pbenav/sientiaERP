<?php

namespace App\Filament\Resources\ExpedicionResource\Pages;

use App\Filament\Resources\ExpedicionResource;
use App\Filament\Resources\ExpedicionResource\Widgets\ExpedicionTotalesWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpedicion extends EditRecord
{
    protected static string $resource = ExpedicionResource::class;

    // Al guardar, vuelve al listado
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Widget de totales ARRIBA del formulario (alertas de pendientes)
    protected function getHeaderWidgets(): array
    {
        return [
            ExpedicionTotalesWidget::class,
        ];
    }
}
