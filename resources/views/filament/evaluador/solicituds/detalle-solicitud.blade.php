<x-filament-panels::page>
    @if ($this->soloLectura())
        <x-filament::section icon="heroicon-o-information-circle" compact>
            <x-slot name="heading">Solo lectura</x-slot>
            Esta solicitud está en estado «{{ \App\Enums\EstadoSolicitud::labelDe($record->estado) }}» y no admite cambios del evaluador.
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
