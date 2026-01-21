<div>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-6">
            <x-filament-actions::actions :actions="$this->getCachedFormActions()" />
        </div>
    </form>
</div>
