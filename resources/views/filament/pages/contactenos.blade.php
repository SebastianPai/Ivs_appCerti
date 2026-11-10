<x-filament-panels::page>
    <form wire:submit="enviar" class="grid gap-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                Enviar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
