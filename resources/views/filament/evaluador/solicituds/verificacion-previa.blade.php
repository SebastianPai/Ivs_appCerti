<x-filament-panels::page>
    @include('filament.evaluador.solicituds.pasos', ['actual' => 1])

    <div class="mx-auto grid w-full max-w-2xl gap-6">
        <x-filament::section icon="heroicon-o-shield-check">
            <x-slot name="heading">Filtro de seguridad obligatorio</x-slot>
            <x-slot name="description">
                Solicitud #{{ $record->id }} · {{ $record->placa() }} · {{ $record->user?->name }}
            </x-slot>

            <div class="grid gap-6">
                {{-- 1. Declaración --}}
                {{ $this->form }}

                {{-- 2. Ubicación --}}
                <div
                    x-data="{
                        cargando: false,
                        error: null,
                        capturar() {
                            this.error = null;
                            if (! window.isSecureContext) {
                                this.error = 'El GPS solo funciona si el sitio abre con https://';
                                return;
                            }
                            if (! navigator.geolocation) {
                                this.error = 'Este navegador no permite obtener la ubicación.';
                                return;
                            }
                            this.cargando = true;
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    $wire.guardarGeolocalizacion({
                                        lat: pos.coords.latitude,
                                        lng: pos.coords.longitude,
                                        accuracy: pos.coords.accuracy,
                                    }).finally(() => this.cargando = false);
                                },
                                (err) => {
                                    this.cargando = false;
                                    this.error = {
                                        1: 'Permiso denegado. Habilite la ubicación para este sitio en la configuración del navegador.',
                                        2: 'No se pudo determinar la ubicación. Active el GPS del teléfono.',
                                        3: 'El GPS tardó demasiado. Intente de nuevo, idealmente a cielo abierto.',
                                    }[err.code] ?? 'No se pudo obtener la ubicación.';
                                },
                                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
                            );
                        },
                    }"
                    @class([
                        'rounded-xl border p-4',
                        'border-success-600/30 bg-success-50 dark:bg-success-500/10' => $geolocalizacion_ok,
                        'border-gray-950/10 dark:border-white/10' => ! $geolocalizacion_ok,
                    ])
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <x-filament::icon
                                :icon="$geolocalizacion_ok ? 'heroicon-o-check-circle' : 'heroicon-o-map-pin'"
                                @class(['h-6 w-6 shrink-0', 'text-success-600' => $geolocalizacion_ok, 'text-gray-400' => ! $geolocalizacion_ok])
                            />
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $geolocalizacion_ok ? 'Ubicación registrada' : 'Ubicación en el sitio de inspección' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    @if ($geolocalizacion_ok)
                                        Precisión aproximada: ±{{ $precision !== null ? round($precision) : '?' }} m
                                    @else
                                        Se registra para certificar que la inspección se hizo en el taller.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <x-filament::button
                            x-on:click="capturar()"
                            x-bind:disabled="cargando"
                            :color="$geolocalizacion_ok ? 'gray' : 'primary'"
                            icon="heroicon-o-map-pin"
                            class="w-full sm:w-auto"
                        >
                            <span x-show="! cargando">{{ $geolocalizacion_ok ? 'Volver a capturar' : 'Capturar ubicación' }}</span>
                            <span x-show="cargando" x-cloak>Buscando señal…</span>
                        </x-filament::button>
                    </div>

                    <p x-show="error" x-text="error" x-cloak class="mt-3 text-sm font-medium text-danger-600 dark:text-danger-400"></p>
                </div>
            </div>
        </x-filament::section>

        <div class="ivs-sticky-actions">
            <x-filament::button
                wire:click="continuar"
                size="lg"
                icon="heroicon-m-arrow-right"
                icon-position="after"
                :disabled="! $geolocalizacion_ok"
                class="w-full"
            >
                Continuar a la inspección
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
