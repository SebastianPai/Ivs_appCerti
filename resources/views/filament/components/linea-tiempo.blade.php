{{-- Historial de una solicitud. $actividades: colección de App\Models\Actividad (con user) --}}
@if ($actividades->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aún no hay movimientos registrados. El historial se lleva desde octubre de 2026.
    </p>
@else
    <ol class="ivs-linea">
        @foreach ($actividades as $actividad)
            @php
                $icono = match ($actividad->evento) {
                    'creado' => 'heroicon-m-plus-circle',
                    'estado' => 'heroicon-m-arrow-right-circle',
                    'documento' => 'heroicon-m-paper-clip',
                    'inspeccion' => 'heroicon-m-clipboard-document-check',
                    'sincronizacion' => 'heroicon-m-cloud-arrow-up',
                    'aviso' => 'heroicon-m-bell-alert',
                    'eliminado' => 'heroicon-m-trash',
                    default => 'heroicon-m-pencil-square',
                };
                $color = match ($actividad->eventoColor()) {
                    'primary' => 'text-primary-600 dark:text-primary-400',
                    'success' => 'text-success-600 dark:text-success-400',
                    'danger' => 'text-danger-600 dark:text-danger-400',
                    'info' => 'text-info-600 dark:text-info-400',
                    'warning' => 'text-warning-600 dark:text-warning-400',
                    default => 'text-gray-400',
                };
            @endphp
            <li class="ivs-linea__item">
                <span class="ivs-linea__punto">
                    <x-filament::icon :icon="$icono" @class(['h-5 w-5', $color]) />
                </span>
                <p class="ivs-linea__fecha">
                    <time datetime="{{ $actividad->created_at?->toIso8601String() }}">{{ $actividad->created_at?->format('d/m/Y h:i a') }}</time>
                    · {{ $actividad->user?->name ?? 'Sistema' }}
                </p>
                <p class="ivs-linea__texto">{{ $actividad->descripcion }}</p>
            </li>
        @endforeach
    </ol>
@endif
