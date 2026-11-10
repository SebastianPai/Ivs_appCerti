<x-filament-panels::page>
    <div class="grid gap-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="verVehiculos" icon="heroicon-o-truck" :outlined="$this->vista !== 'vehiculos'">
                Vehículos Certificados
            </x-filament::button>
            <x-filament::button wire:click="verGenerados" icon="heroicon-o-document-check" :outlined="$this->vista !== 'generados'">
                Certificados Generados
            </x-filament::button>
        </div>
    </div>

    @if ($this->vista === 'vehiculos')
        @php($filas = $this->vehiculosFiltrados())
        <x-filament::section>
            <x-slot name="heading">Vehículos Certificados</x-slot>
            <x-slot name="description">{{ $this->vehiculos->count() }} vehículo(s) en el mes</x-slot>

            <div class="grid gap-y-4">
                <x-filament::button wire:click="generar" color="warning" icon="heroicon-o-arrow-down-tray" class="w-full" :disabled="$this->vehiculos->isEmpty()">
                    Generar certificado
                </x-filament::button>

                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar" />
                </x-filament::input.wrapper>

                <div style="overflow-x: auto; max-width: 100%;">
                    <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(128, 128, 128, .25);">
                                <th style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">Id certificado</th>
                                <th style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">Placa o VIN</th>
                                <th style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">Fecha Certificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $solicitud)
                                <tr style="border-bottom: 1px solid rgba(128, 128, 128, .25);" wire:key="vehiculo-{{ $solicitud->id }}">
                                    <td style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">{{ $solicitud->codigo }}</td>
                                    <td style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">{{ $solicitud->placa() }}</td>
                                    <td style="padding: .5rem .75rem; white-space: nowrap; text-align: start;">{{ $solicitud->fecha_aprobacion?->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No hay vehículos certificados para este taller en el mes.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-filament::section>
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
