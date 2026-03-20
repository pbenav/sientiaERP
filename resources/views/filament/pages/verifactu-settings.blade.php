<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-x-3">
            <x-filament::button type="submit">
                Guardar Configuración
            </x-filament::button>
            <x-filament::link href="{{ \App\Filament\Pages\SettingsPage::getUrl() }}" color="gray">
                Volver a General
            </x-filament::link>
        </div>
    </form>
</x-filament-panels::page>
