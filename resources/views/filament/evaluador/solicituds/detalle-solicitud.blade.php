<x-filament-panels::page>
    @if ($this->soloLectura())
        <x-filament::section icon="heroicon-o-information-circle" compact>
            <x-slot name="heading">Solo lectura</x-slot>
            @if (! auth()->user()->hasRole('evaluador'))
                Está viendo esta solicitud como {{ auth()->user()->getRoleNames()->first() ?? 'usuario' }}.
                La inspección (GPS, chip, fotos y checklist) solo la puede hacer un usuario con rol evaluador asignado a este taller;
                inicie sesión como evaluador (por ejemplo evaluador@ivs.com) para evaluarla.
            @else
                Esta solicitud está en estado «{{ \App\Enums\EstadoSolicitud::labelDe($record->estado) }}» y no admite cambios del evaluador.
            @endif
        </x-filament::section>
    @else
        @include('filament.evaluador.solicituds.pasos', ['actual' => 2])
    @endif

    @php($alertas = $record->alertasEquipos())
    @foreach (['reguladores' => 'Alerta de Reguladores', 'cilindros' => 'Alerta de Cilindros'] as $clave => $titulo)
        @if ($alertas[$clave])
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger" compact>
                <x-slot name="heading">{{ $titulo }}</x-slot>
                <ul class="grid gap-1 text-sm text-danger-700 dark:text-danger-400">
                    @foreach ($alertas[$clave] as $linea)
                        <li>{{ $linea }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endforeach

    <form wire:submit.prevent class="grid gap-y-6">
        {{ $this->form }}
    </form>

    @unless ($this->soloLectura())
        <div class="ivs-sticky-actions">
            {{-- Dibujar la acción no comprueba su visibilidad: sin esto «Aceptar Cambio de chip» salía en cualquier servicio --}}
            @foreach (['cancelarAction', 'devolverAlTallerAction', 'aceptarCambioChipAction', 'continuarAction'] as $accion)
                @if ($this->{$accion}->isVisible())
                    {{ $this->{$accion} }}
                @endif
            @endforeach
        </div>
    @endunless

    <x-filament-actions::modals />
</x-filament-panels::page>
