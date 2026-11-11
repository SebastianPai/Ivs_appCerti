<x-filament-panels::page>
    <form wire:submit="guardar" class="grid gap-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-check">
                Guardar contraseña
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
