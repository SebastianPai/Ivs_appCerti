{{-- Lectura del checklist del evaluador. Las preguntas salen de App\Support\ChecklistTecnico. --}}
@php
    use App\Support\ChecklistTecnico;
    $resultado = [
        'c' => ['Cumple', 'success'],
        'nc' => ['No cumple', 'danger'],
        'na' => ['N/A', 'gray'],
    ];
    $noConformes = ChecklistTecnico::noConformidades($datos);
@endphp

@if (empty($datos))
    <p class="text-sm text-gray-500 dark:text-gray-400">El evaluador no ha registrado el checklist.</p>
@else
    <div class="grid gap-6">
        @if ($noConformes)
            <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                <strong>{{ count($noConformes) }} no conformidad(es):</strong>
                <ul class="mt-1 list-disc ps-5">
                    @foreach ($noConformes as $nc)
                        <li>{{ $nc }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($datos['verificacion_cilindros']))
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Cilindros</h3>
                <div class="grid gap-2">
                    @foreach ($datos['verificacion_cilindros'] as $c)
                        <div class="ivs-fila">
                            <span class="font-mono font-semibold">{{ $c['numero_serie'] ?? 's/n' }}</span>
                            <span>Ultraliviano: <strong>{{ ($c['ultra_liviano'] ?? null) === 'si' ? 'Sí' : 'No' }}</strong></span>
                            <span>{{ collect($c['ubicacion'] ?? [])->map(fn ($u) => ChecklistTecnico::UBICACIONES_CILINDRO[$u] ?? $u)->join(', ') ?: '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach (ChecklistTecnico::secciones() as $seccion)
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $seccion['titulo'] }}</h3>
                <div class="grid gap-2">
                    @foreach ($seccion['items'] as $item)
                        @php
                            [$texto, $color] = $resultado[$datos[$item['id']] ?? ''] ?? ['Sin respuesta', 'warning'];
                            $medicion = $datos[$item['id'].'_valor'] ?? null;
                        @endphp
                        <div class="ivs-fila">
                            <div class="min-w-0 flex-1">
                                <span class="ivs-numeral">{{ $item['numeral'] }}</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['texto'] }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                @if (($item['medicion'] ?? false) && filled($medicion))
                                    <span class="font-mono text-sm">{{ $medicion }} cm</span>
                                @endif
                                <x-filament::badge :color="$color">{{ $texto }}</x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
