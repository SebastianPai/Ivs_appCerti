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

    <form wire:submit.prevent class="grid gap-y-6">
        {{ $this->form }}
    </form>

    @unless ($this->soloLectura())
        <div class="ivs-sticky-actions">
            {{ $this->devolverAlTallerAction }}
            {{ $this->continuarAction }}
        </div>
    @endunless

    <x-filament-actions::modals />
</x-filament-panels::page>
