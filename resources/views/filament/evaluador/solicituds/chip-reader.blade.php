{{--
    Lector de chip. Soporta:
    1) NFC del celular (Web NFC: Chrome en Android, sitio en https). Lee el registro de texto
       del chip y, si no tiene, usa el número de serie (UID).
    2) Lector USB/Bluetooth tipo "teclado" (PC): basta con enfocar el campo y acercar el chip;
       el lector escribe el código. El botón "Usar lector USB" solo enfoca el campo.
    3) Digitación manual en el mismo campo.
--}}
<div
    x-data="{
        soportaNfc: 'NDEFReader' in window,
        seguro: window.isSecureContext,
        leyendo: false,
        mensaje: null,
        error: null,
        abort: null,
        input() { return document.getElementById('ivs-chip-input') },
        escribir(valor) {
            const codigo = String(valor).trim().toUpperCase();
            $wire.set('data.chip_codigo', codigo);
            this.mensaje = 'Chip leído: ' + codigo;
            if (navigator.vibrate) navigator.vibrate(150);
        },
        async leerNfc() {
            this.error = null; this.mensaje = null;
            if (! this.seguro) { this.error = 'El NFC solo funciona si el sitio abre con https://'; return; }
            try {
                this.abort = new AbortController();
                const lector = new NDEFReader();
                await lector.scan({ signal: this.abort.signal });
                this.leyendo = true;
                lector.onreadingerror = () => { this.error = 'No se pudo leer el chip. Acérquelo de nuevo.'; };
                lector.onreading = ({ message, serialNumber }) => {
                    const texto = [...message.records]
                        .filter(r => r.recordType === 'text')
                        .map(r => new TextDecoder(r.encoding || 'utf-8').decode(r.data))[0];
                    this.escribir(texto || (serialNumber || '').replaceAll(':', ''));
                    this.detener();
                };
            } catch (e) {
                this.leyendo = false;
                this.error = e.name === 'NotAllowedError'
                    ? 'Permiso de NFC denegado. Actívelo en la configuración del navegador.'
                    : 'No fue posible iniciar el NFC: ' + e.message;
            }
        },
        detener() { this.abort?.abort(); this.leyendo = false; },
        usarUsb() {
            this.error = null;
            this.mensaje = 'Campo listo: acerque el chip al lector USB.';
            const el = this.input(); el?.focus(); el?.select();
        },
    }"
    x-on:livewire:navigating.window="detener()"
    class="flex flex-col gap-3"
>
    <div class="flex flex-col gap-2 sm:flex-row">
        <template x-if="soportaNfc">
            <x-filament::button type="button" icon="heroicon-o-signal" x-on:click="leyendo ? detener() : leerNfc()" class="w-full sm:w-auto">
                <span x-text="leyendo ? 'Esperando chip… (toque para cancelar)' : 'Leer con NFC del celular'"></span>
            </x-filament::button>
        </template>

        <x-filament::button type="button" color="gray" icon="heroicon-o-computer-desktop" x-on:click="usarUsb()" class="w-full sm:w-auto">
            Usar lector USB
        </x-filament::button>
    </div>

    <div x-show="leyendo" x-cloak class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400">
        <x-filament::loading-indicator class="h-5 w-5" />
        Acerque el chip a la parte trasera del celular.
    </div>

    <p x-show="mensaje" x-text="mensaje" x-cloak class="text-sm font-medium text-success-600 dark:text-success-400"></p>
    <p x-show="error" x-text="error" x-cloak class="text-sm font-medium text-danger-600 dark:text-danger-400"></p>

    <p x-show="! soportaNfc" class="text-xs text-gray-500 dark:text-gray-400">
        Este navegador no tiene NFC. Use un lector USB/Bluetooth o digite el código.
        (El NFC funciona en Chrome para Android.)
    </p>
</div>
