{{--
    Lector de chip. Soporta:
    1) NFC del celular (Web NFC: Chrome en Android, sitio en https). Lee el registro de texto
       del chip y, si no tiene, usa el número de serie (UID).
    2) Lector Bluetooth de la plataforma anterior (Web Bluetooth: Chrome o Edge, en PC o Android,
       sitio en https). Se le escribe «2» para leer un iButton o «3» para leer NFC y responde con
       el IDROM de 16 caracteres; si devuelve la misma orden, no pudo leer.
    3) Lector USB/Bluetooth tipo "teclado" (PC): basta con enfocar el campo y acercar el chip;
       el lector escribe el código. El botón "Preparar campo" solo enfoca el campo.
    4) Digitación manual en el mismo campo.

    Tras cada lectura se consulta el estado del chip (route chip.estado) y se muestra: activo,
    inactivo o no registrado. Es solo informativo: la validación real la hace ChipValidationService.

    Parámetros: $campo (ruta del estado en Livewire), $entrada (id del input), $mostrarEstado (bool)
    y $notas (bool: explicaciones para el evaluador bajo el estado).
--}}
@php
    $mostrarEstado = $mostrarEstado ?? true;
    $notas = $notas ?? true;
@endphp
<div
    x-data="{
        soportaNfc: 'NDEFReader' in window,
        soportaBle: 'bluetooth' in navigator,
        seguro: window.isSecureContext,
        metodo: null,
        leyendo: false,
        mensaje: null,
        error: null,
        reintentar: null,
        abort: null,
        ble: null,
        bleNombre: null,
        bleOcupado: false,
        conectando: false,
        codigo: null,
        estado: null,
        consultando: false,

        init() {
            let guardado = null;
            try { guardado = localStorage.getItem('ivs-chip-metodo'); } catch (e) {}
            this.metodo = (guardado && this.disponible(guardado)) ? guardado
                : (this.disponible('nfc') ? 'nfc' : (this.disponible('ble') ? 'ble' : 'usb'));

            // Lector USB tipo teclado o digitación: al terminar (Enter o salir del campo) se consulta el estado
            this.$nextTick(() => this.input()?.addEventListener('change', (e) => {
                const valor = String(e.target.value || '').trim().toUpperCase();
                if (valor && valor !== this.codigo) { this.codigo = valor; this.error = null; this.consultarEstado(valor); }
            }));
        },
        disponible(m) {
            if (m === 'nfc') return this.soportaNfc && this.seguro;
            if (m === 'ble') return this.soportaBle && this.seguro;
            return true;
        },
        motivo(m) {
            const soporta = m === 'nfc' ? this.soportaNfc : this.soportaBle;
            if (! soporta) return m === 'nfc' ? 'Solo en Chrome para Android' : 'Use Chrome o Edge';
            return 'Requiere abrir el sitio con https://';
        },
        elegir(m) {
            if (! this.disponible(m)) return;
            if (this.leyendo) this.detener();
            this.metodo = m;
            this.error = null;
            try { localStorage.setItem('ivs-chip-metodo', m); } catch (e) {}
            if (m === 'usb') this.usarUsb();
        },
        input() { return document.getElementById('{{ $entrada ?? 'ivs-chip-input' }}') },
        escribir(valor) {
            const codigo = String(valor).trim().toUpperCase();
            $wire.set('{{ $campo ?? 'data.chip_codigo' }}', codigo);
            this.codigo = codigo;
            this.error = null;
            this.reintentar = null;
            this.mensaje = 'Chip leído correctamente';
            if (navigator.vibrate) navigator.vibrate(150);
            this.consultarEstado(codigo);
        },
        async consultarEstado(codigo) {
            @if (! $mostrarEstado) return; @endif
            if (! /^[0-9A-Za-z]{1,100}$/.test(codigo)) { this.estado = null; return; }
            this.consultando = true; this.estado = null;
            try {
                const r = await fetch('{{ url('/chip/estado') }}/' + encodeURIComponent(codigo), { headers: { 'Accept': 'application/json' } });
                this.estado = r.ok ? await r.json() : null;
            } catch (e) {
                this.estado = null;
            } finally {
                this.consultando = false;
            }
        },
        fallo(texto, accion) { this.error = texto; this.mensaje = null; this.reintentar = accion; },

        async leerNfc() {
            this.error = null; this.mensaje = null;
            if (! this.seguro) { this.fallo('El NFC solo funciona si el sitio abre con https://', null); return; }
            try {
                this.abort = new AbortController();
                const lector = new NDEFReader();
                await lector.scan({ signal: this.abort.signal });
                this.leyendo = true;
                lector.onreadingerror = () => { this.fallo('No se pudo leer el chip. Acérquelo de nuevo y manténgalo quieto un momento.', () => this.leerNfc()); };
                lector.onreading = ({ message, serialNumber }) => {
                    const texto = [...message.records]
                        .filter(r => r.recordType === 'text')
                        .map(r => new TextDecoder(r.encoding || 'utf-8').decode(r.data))[0];
                    this.escribir(texto || (serialNumber || '').replaceAll(':', ''));
                    this.detener();
                };
            } catch (e) {
                this.leyendo = false;
                this.fallo(e.name === 'NotAllowedError'
                    ? 'Permiso de NFC denegado. Actívelo en la configuración del navegador y vuelva a intentar.'
                    : 'No fue posible iniciar el NFC: ' + e.message, () => this.leerNfc());
            }
        },
        detener() { this.abort?.abort(); this.leyendo = false; },

        async conectarBle() {
            this.error = null; this.mensaje = null;
            if (! this.seguro) { this.fallo('El lector Bluetooth solo funciona si el sitio abre con https://', null); return; }
            this.conectando = true;
            try {
                const servicio = '4fafc201-1fb5-459e-8fcc-c5c9c331914b';
                const dispositivo = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [servicio] });
                dispositivo.addEventListener('gattserverdisconnected', () => {
                    if (this.ble) this.fallo('El lector se desconectó. Verifique que esté encendido y cerca, y conéctelo de nuevo.', () => this.conectarBle());
                    this.ble = null; this.bleNombre = null;
                });
                const gatt = await dispositivo.gatt.connect();
                this.ble = await (await gatt.getPrimaryService(servicio)).getCharacteristic('beb5483e-36e1-4688-b7f5-ea07361b26a8');
                this.bleNombre = dispositivo.name || 'lector';
            } catch (e) {
                // NotFoundError = el usuario cerró la lista sin elegir: no es un error
                if (e.name !== 'NotFoundError') this.fallo('No se pudo conectar el lector: ' + e.message + '. Verifique que esté encendido y sin conectar a otro equipo.', () => this.conectarBle());
            } finally {
                this.conectando = false;
            }
        },
        desconectarBle() {
            const dispositivo = Alpine.raw(this.ble)?.service?.device;
            this.ble = null; this.bleNombre = null;
            try { dispositivo?.gatt?.disconnect(); } catch (e) {}
        },
        async leerBle(orden) {
            this.error = null; this.mensaje = null; this.bleOcupado = true;
            try {
                // Alpine envuelve el objeto en un Proxy y el navegador solo acepta el original
                const canal = Alpine.raw(this.ble);
                await canal.writeValue(new TextEncoder().encode(orden));
                // El lector tarda en responder: mientras devuelva la orden, se vuelve a leer
                let texto = '';
                for (let intento = 0; intento < 6; intento++) {
                    texto = new TextDecoder().decode(await canal.readValue()).trim();
                    if (/^[0-9A-Za-z]{16}$/.test(texto)) break;
                    await new Promise((listo) => setTimeout(listo, 400));
                }
                if (/^[0-9A-Za-z]{16}$/.test(texto)) {
                    this.escribir(texto);
                } else {
                    this.fallo('No se pudo leer el chip. Acérquelo de nuevo; si el problema continúa, es posible que el chip no funcione.', () => this.leerBle(orden));
                }
            } catch (e) {
                this.fallo('Se perdió la conexión con el lector. Conéctelo de nuevo.', () => this.conectarBle());
                this.ble = null; this.bleNombre = null;
            } finally {
                this.bleOcupado = false;
            }
        },
        usarUsb() {
            this.error = null;
            this.mensaje = null;
            const el = this.input(); el?.focus(); el?.select();
        },
    }"
    x-on:livewire:navigating.window="detener()"
    class="ivs-chip"
>
    {{-- 1. Cómo leer --}}
    <p class="ivs-chip__paso"><span>1</span> Elija cómo leer el chip</p>
    <div class="ivs-chip__metodos" role="radiogroup" aria-label="Forma de lectura">
        @foreach ([
            'nfc' => ['heroicon-o-device-phone-mobile', 'NFC del celular', 'Acerque el chip al teléfono'],
            'ble' => ['heroicon-o-signal', 'Lector Bluetooth', 'iButton o NFC con el lector'],
            'usb' => ['heroicon-o-computer-desktop', 'Lector USB o digitar', 'El código se escribe en el campo'],
        ] as $clave => [$icono, $titulo, $detalle])
            <button
                type="button"
                role="radio"
                x-on:click="elegir('{{ $clave }}')"
                x-bind:aria-checked="metodo === '{{ $clave }}'"
                x-bind:disabled="! disponible('{{ $clave }}')"
                x-bind:class="{ 'ivs-chip__metodo--activo': metodo === '{{ $clave }}' }"
                class="ivs-chip__metodo"
            >
                <x-filament::icon :icon="$icono" class="ivs-chip__metodo-icono" />
                <span class="ivs-chip__metodo-texto">
                    <span class="ivs-chip__metodo-titulo">{{ $titulo }}</span>
                    <span class="ivs-chip__metodo-detalle" x-text="disponible('{{ $clave }}') ? @js($detalle) : motivo('{{ $clave }}')"></span>
                </span>
                @if ($clave === 'ble')
                    <span x-show="ble" x-cloak class="ivs-chip__punto" title="Conectado"></span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- 2. Leer --}}
    <p class="ivs-chip__paso"><span>2</span> <span x-text="metodo === 'ble' && ! ble ? 'Conecte el lector' : 'Lea el chip'"></span></p>
    <div class="ivs-chip__panel">
        {{-- NFC del celular --}}
        <div x-show="metodo === 'nfc'" x-cloak class="ivs-chip__contenido">
            <div x-show="! leyendo" class="ivs-chip__fila">
                <p class="ivs-chip__ayuda">Pulse <b>Empezar a leer</b> y acerque el chip a la parte trasera del celular, cerca de la cámara.</p>
                <x-filament::button type="button" icon="heroicon-o-signal" x-on:click="leerNfc()" class="ivs-chip__accion">
                    Empezar a leer
                </x-filament::button>
            </div>
            <div x-show="leyendo" class="ivs-chip__esperando">
                <span class="ivs-chip__onda"><x-filament::icon icon="heroicon-o-cpu-chip" class="h-6 w-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="ivs-chip__esperando-titulo">Esperando el chip…</p>
                    <p class="ivs-chip__ayuda">Acérquelo a la parte trasera del celular y manténgalo quieto.</p>
                </div>
                <x-filament::button type="button" color="gray" x-on:click="detener()">Cancelar</x-filament::button>
            </div>
        </div>

        {{-- Lector Bluetooth --}}
        <div x-show="metodo === 'ble'" x-cloak class="ivs-chip__contenido">
            <template x-if="! ble">
                <div class="ivs-chip__fila">
                    <ol class="ivs-chip__lista">
                        <li>Encienda el lector y acérquelo al equipo.</li>
                        <li>Pulse <b>Conectar lector</b> y elíjalo en la lista que abre el navegador.</li>
                    </ol>
                    <x-filament::button type="button" icon="heroicon-o-link" x-on:click="conectarBle()" x-bind:disabled="conectando" class="ivs-chip__accion">
                        <span x-text="conectando ? 'Conectando…' : 'Conectar lector'"></span>
                    </x-filament::button>
                </div>
            </template>
            <template x-if="ble">
                <div class="flex flex-col gap-3">
                    <div class="ivs-chip__conectado">
                        <span class="ivs-chip__punto"></span>
                        <span class="min-w-0 flex-1 truncate">Conectado a <b x-text="bleNombre"></b></span>
                        <button type="button" x-on:click="desconectarBle()" class="ivs-chip__enlace">Desconectar</button>
                    </div>
                    <div x-show="! bleOcupado" class="ivs-chip__botones">
                        <x-filament::button type="button" icon="heroicon-o-key" x-on:click="leerBle('2')">Leer iButton</x-filament::button>
                        <x-filament::button type="button" icon="heroicon-o-signal" x-on:click="leerBle('3')">Leer NFC con el lector</x-filament::button>
                    </div>
                    <div x-show="bleOcupado" class="ivs-chip__esperando">
                        <span class="ivs-chip__onda"><x-filament::icon icon="heroicon-o-cpu-chip" class="h-6 w-6" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="ivs-chip__esperando-titulo">Leyendo…</p>
                            <p class="ivs-chip__ayuda">Mantenga el chip en el lector.</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Lector USB / digitación --}}
        <div x-show="metodo === 'usb'" x-cloak class="ivs-chip__contenido">
            <div class="ivs-chip__fila">
                <p class="ivs-chip__ayuda">Pulse <b>Preparar campo</b> y acerque el chip al lector USB: el código se escribe solo en el campo del chip. También puede digitarlo.</p>
                <x-filament::button type="button" color="gray" icon="heroicon-o-cursor-arrow-rays" x-on:click="usarUsb()" class="ivs-chip__accion">
                    Preparar campo
                </x-filament::button>
            </div>
        </div>
    </div>

    {{-- Resultado --}}
    <div x-show="error" x-cloak class="ivs-chip__resultado ivs-chip__resultado--error" role="alert">
        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
        <p class="min-w-0 flex-1" x-text="error"></p>
        <button type="button" x-show="reintentar" x-on:click="reintentar && reintentar()" class="ivs-chip__enlace">Reintentar</button>
    </div>

    <div x-show="codigo && ! error" x-cloak class="ivs-chip__resultado" aria-live="polite">
        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 shrink-0 text-success-600 dark:text-success-400" />
        <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="mensaje || 'Código del chip'"></p>
            <p class="ivs-chip__codigo" x-text="codigo"></p>
        </div>
        @if ($mostrarEstado)
            <span x-show="consultando" class="text-xs text-gray-500 dark:text-gray-400">Consultando…</span>
            <template x-if="estado && ! consultando">
                <span
                    class="ivs-chip__estado"
                    x-bind:class="! estado.registrado ? 'ivs-chip__estado--gris' : (estado.activo ? 'ivs-chip__estado--ok' : 'ivs-chip__estado--aviso')"
                    x-text="! estado.registrado ? 'No registrado' : (estado.activo ? 'Activo' : 'Inactivo')"
                ></span>
            </template>
        @endif
    </div>
    @if ($mostrarEstado && $notas)
        <p x-show="estado && ! consultando && estado.registrado && ! estado.activo && ! error" x-cloak class="ivs-chip__nota">
            <span x-text="estado?.permite_inactivos
                ? 'Este chip está inactivo. La configuración actual permite continuar con chips inactivos.'
                : 'Este chip está inactivo: un servicio con chip nuevo no lo aceptará. Una revisión sí puede continuar con él.'"></span>
            <span x-show="estado?.vehiculo" x-text="' Último vehículo: ' + estado?.vehiculo + '.'"></span>
        </p>
        <p x-show="estado && ! consultando && ! estado.registrado && ! error" x-cloak class="ivs-chip__nota">
            Este chip no está en el inventario. En una revisión se acepta el chip que trae el vehículo; un chip nuevo debe estar registrado.
        </p>
    @endif
</div>
