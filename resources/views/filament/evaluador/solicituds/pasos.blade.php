{{-- Indicador de progreso del evaluador. Uso: @include('...pasos', ['actual' => 1]) --}}
@php
    $pasos = [1 => 'Filtro de seguridad', 2 => 'Chip y fotos', 3 => 'Checklist técnico'];
@endphp
<nav aria-label="Progreso de la evaluación" class="ivs-pasos">
    @foreach ($pasos as $n => $titulo)
        <div @class(['ivs-paso', 'ivs-paso--hecho' => $n < $actual, 'ivs-paso--actual' => $n === $actual]) @if($n === $actual) aria-current="step" @endif>
            <span class="ivs-paso__num">
                @if ($n < $actual)
                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                @else
                    {{ $n }}
                @endif
            </span>
            <span class="ivs-paso__txt">{{ $titulo }}</span>
        </div>
    @endforeach
</nav>
