<x-filament-panels::page>
    @php($user = auth()->user())

    @if ($user->debeActualizarRegistro())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">Alerta</x-slot>

            @if ($user->camara_comercio && $user->fecha_vencimiento === null)
                El archivo ya fue cargado. Falta que el administrador asigne la fecha de vencimiento del registro; hasta entonces no podrá usar la aplicación.
            @else
                El registro de Industria y Comercio está vencido. Por favor, actualiza el registro y cárgalo a continuación
            @endif
        </x-filament::section>
    @elseif ($user->fecha_vencimiento)
        <x-filament::section compact>
            Registro vigente hasta el {{ $user->fecha_vencimiento->format('d/m/Y') }}.
        </x-filament::section>
    @endif

    <form wire:submit="guardar" class="grid gap-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                Cargar archivo
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
