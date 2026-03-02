<?php

namespace App\Filament\Resources\ImportDraftResource\Pages;

use App\Filament\Resources\ImportDraftResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

class ListImportDrafts extends ListRecords
{
    protected static string $resource = ImportDraftResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending'))
                ->badge(\App\Models\ImportDraft::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'confirmed' => Tab::make('Confirmados')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'confirmed')),

            'rejected' => Tab::make('Rechazados')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),

            'all' => Tab::make('Todos'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'pending';
    }
}
