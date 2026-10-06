@if (! $geo)
    <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
        No se registraron coordenadas GPS para esta evaluación.
    </div>
@else
    @php
        $lat = (float) $geo->latitud;
        $lng = (float) $geo->longitud;
    @endphp
    <div class="grid gap-3">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-700 dark:text-gray-300">
            <span><strong>Lat:</strong> {{ $lat }}</span>
            <span><strong>Lng:</strong> {{ $lng }}</span>
            <span><strong>Precisión:</strong> ±{{ $geo->precision_m !== null ? round($geo->precision_m) : '?' }} m</span>
            <span><strong>Registrada:</strong> {{ \Illuminate\Support\Carbon::parse($geo->registrado_en)->format('d/m/Y H:i') }}</span>
            <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="font-medium text-primary-600 underline">Abrir en Google Maps</a>
        </div>
        <iframe
            title="Ubicación de la inspección"
            src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&z=16&output=embed"
            class="h-72 w-full rounded-lg border-0"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>
@endif
