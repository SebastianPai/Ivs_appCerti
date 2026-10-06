<x-filament-panels::page>
    @if ($record->estado === \App\Enums\EstadoSolicitud::DevueltaTaller->value && filled($record->observacion_devolucion))
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">El evaluador solicitó correcciones</x-slot>
            <p class="text-sm" style="white-space: pre-line">{{ $record->observacion_devolucion }}</p>
        </x-filament::section>
    @endif

    <form wire:submit="save" class="grid gap-y-6">
        {{ $this->form }}

        {{-- Barra fija abajo en celular para no tener que hacer scroll hasta el final --}}
        <div class="ivs-sticky-actions">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-paper-airplane" class="w-full sm:w-auto">
                {{ $record->estado === \App\Enums\EstadoSolicitud::DevueltaTaller->value ? 'Enviar correcciones' : 'Guardar documentación' }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
