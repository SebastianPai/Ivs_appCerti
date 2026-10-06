<x-filament-panels::page>
    @include('filament.evaluador.solicituds.pasos', ['actual' => 3])

    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <div class="flex justify-start">
        {{ $this->guardarBorradorAction }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
