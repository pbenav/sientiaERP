<x-filament-panels::page>
    <x-filament-forms::base-wire-form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-end gap-x-3">
            <x-filament::button type="submit" size="lg">
                Generar Datos de Prueba
            </x-filament::button>
        </div>
    </x-filament-forms::base-wire-form>
</x-filament-panels::page>
