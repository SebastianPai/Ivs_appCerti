{{-- Layout de la pantalla de ingreso: solo la base de Filament (estilos, scripts, notificaciones), sin barra superior. --}}
<x-filament-panels::layout.base :livewire="$livewire ?? null">
    {{ $slot }}
</x-filament-panels::layout.base>
