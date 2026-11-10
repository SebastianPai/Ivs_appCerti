<x-filament-panels::page>
    <form wire:submit="exportar" class="grid gap-y-6">
        {{ $this->form }}

        {{-- Mismo orden de botones del original: Filtrar, Exportar, Limpiar filtro --}}
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" wire:click="filtrar" icon="heroicon-o-funnel" color="gray">
                Filtrar
            </x-filament::button>
            <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                Exportar
            </x-filament::button>
            <x-filament::button type="button" wire:click="limpiar" icon="heroicon-o-x-mark" color="gray" outlined>
                Limpiar filtro
            </x-filament::button>
        </div>
    </form>

    @if ($this->vista)
        <x-filament::section>
            <x-slot name="heading">Resultado</x-slot>
            <x-slot name="description">
                {{ $this->vista['total'] }} registro(s).
                @if ($this->vista['total'] > count($this->vista['filas']))
                    Se muestran los primeros {{ count($this->vista['filas']) }}; el archivo los trae todos.
                @endif
            </x-slot>

            <div style="overflow-x: auto; max-width: 100%;">
                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(128, 128, 128, .25);">
                            @foreach ($this->vista['encabezados'] as $encabezado)
                                <th style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">{{ $encabezado }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->vista['filas'] as $fila)
                            <tr style="border-bottom: 1px solid rgba(128, 128, 128, .25);">
                                @foreach ($fila as $celda)
                                    <td style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">{{ $celda }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>