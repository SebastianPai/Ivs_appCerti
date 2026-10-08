<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-building-storefront" heading="Talleres" description="Los 10 talleres con más solicitudes en el periodo. Una tasa de devolución alta indica que el taller necesita acompañamiento.">
        <div class="ivs-tabla-wrap">
            <table class="ivs-tabla">
                <thead>
                    <tr>
                        <th>Taller</th>
                        <th class="text-right">Creadas</th>
                        <th class="text-right">Aprobadas</th>
                        <th class="text-right">Devueltas</th>
                        <th class="text-right">Tasa de devolución</th>
                        <th class="text-right">Certificados por renovar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($talleres as $fila)
                        <tr>
                            <td class="font-medium">{{ $fila['nombre'] }}</td>
                            <td class="text-right tabular-nums">{{ $fila['creadas'] }}</td>
                            <td class="text-right tabular-nums">{{ $fila['aprobadas'] }}</td>
                            <td class="text-right tabular-nums">{{ $fila['devueltas'] }}</td>
                            <td class="text-right">
                                <span @class([
                                    'inline-flex items-center gap-1 tabular-nums',
                                    'font-semibold text-danger-600 dark:text-danger-400' => $fila['tasa'] > 30,
                                ])>
                                    @if ($fila['tasa'] > 30)
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                                    @endif
                                    {{ $fila['tasa'] }} %
                                </span>
                            </td>
                            <td class="text-right tabular-nums">{{ $fila['por_renovar'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-500">No hay solicitudes en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
