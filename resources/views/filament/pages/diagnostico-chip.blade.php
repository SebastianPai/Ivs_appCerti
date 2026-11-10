<x-filament-panels::page>
    <form wire:submit="consultar" class="grid gap-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                Consultar chip
            </x-filament::button>
        </div>
    </form>

    @if ($this->resultado)
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($this->resultado as $titulo => $filas)
                <x-filament::section compact>
                    <x-slot name="heading">{{ $titulo }}</x-slot>
                    <dl class="grid gap-2 text-sm">
                        @foreach ($filas as $etiqueta => $valor)
                            <div class="flex justify-between gap-4 border-b border-gray-100 pb-2 last:border-0 dark:border-white/10">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $etiqueta }}</dt>
                                <dd class="text-end font-medium text-gray-950 dark:text-white">{{ filled($valor) ? $valor : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
