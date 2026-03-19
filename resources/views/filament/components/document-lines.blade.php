<div class="py-3">
    @php
        $record = isset($record) ? $record : (method_exists($this, 'getRecord') ? $this->getRecord() : null);
    @endphp

    @if($record && $record->exists)
        @livewire(\App\Filament\RelationManagers\LineasRelationManager::class, ['ownerRecord' => $record, 'pageClass' => $this::class], key('lines-'.$record->id))
    @else
        <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 text-center text-gray-500">
            <p>Guarde el documento para habilitar la gestión de líneas.</p>
        </div>
    @endif
</div>
