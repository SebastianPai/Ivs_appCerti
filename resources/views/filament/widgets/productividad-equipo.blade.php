<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-user-group" heading="Productividad del equipo" description="Trabajo realizado en el periodo seleccionado.">
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Evaluadores</h3>
                <div class="ivs-tabla-wrap">
                    <table class="ivs-tabla">
                        <thead>
                            <tr>
                                <th>Evaluador</th>
                                <th class="text-right">Enviadas a revisión</th>
                                <th class="text-right">Aprobadas</th>
                                <th class="text-right">Devueltas al taller</th>
                                <th class="text-right">Correcciones del revisor</th>
                                <th class="text-right">Tiempo promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($evaluadores as $fila)
                                <tr>
                                    <td class="font-medium">{{ $fila['nombre'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['enviadas'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['aprobadas'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['devueltas_taller'] }}</td>
                                    <td @class(['text-right tabular-nums', 'font-semibold text-danger-600 dark:text-danger-400' => $fila['correcciones'] > 0])>{{ $fila['correcciones'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['tiempo'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-gray-500">No hay evaluadores registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Revisores</h3>
                <div class="ivs-tabla-wrap">
                    <table class="ivs-tabla">
                        <thead>
                            <tr>
                                <th>Revisor</th>
                                <th class="text-right">Aprobadas</th>
                                <th class="text-right">Devueltas</th>
                                <th class="text-right">Tiempo promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($revisores as $fila)
                                <tr>
                                    <td class="font-medium">{{ $fila['nombre'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['aprobadas'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['devueltas'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fila['tiempo'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-gray-500">No hay revisores registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Las devoluciones y correcciones se cuentan desde que se activó el registro de auditoría (octubre de 2026).
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
