<x-filament-panels::page>
    @include('filament.evaluador.solicituds.pasos', ['actual' => 3])

    @if ($this->ocultaItems())
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600 dark:text-gray-400">
            <span>
                {{ $this->mostrarTodos
                    ? 'Está viendo todos los ítems.'
                    : 'Los ítems que ya están en «Cumple» o «N/A» están ocultos: solo ve los que debe contestar o medir.' }}
            </span>
            {{ $this->alternarItemsAction }}
        </div>
    @endif

    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <div class="flex justify-start">
        {{ $this->guardarBorradorAction }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
