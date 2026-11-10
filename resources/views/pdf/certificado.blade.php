<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de evaluación {{ $record->codigo }}</title>
    {{--
        Mismo diseño del certificado de la plataforma anterior (FO-OP-026 V11): hoja carta horizontal,
        tarjeta centrada con borde rojo, marca de agua tenue detrás y QR abajo al centro.
        El QR aquí sí verifica: lleva a la página pública del certificado.
    --}}
    <style>
        @page { margin: 0.9cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 7.5pt; line-height: 1.35; color: #222; }
        .tarjeta { width: 400pt; margin: 0 auto; border: 1.5pt solid #cc0000; padding: 12pt 16pt 10pt; }
        .logos { text-align: center; margin-bottom: 4pt; }
        .logos img { height: 30pt; margin: 0 6pt; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 7pt; }
        .norma { background-color: #f5f5f5; border-left: 3pt solid #cccccc; padding: 5pt 6pt; text-align: center; margin-bottom: 8pt; }
        h2 { font-size: 9pt; margin: 8pt 0 4pt; padding-bottom: 2pt; border-bottom: 0.7pt solid #cccccc; }
        h3 { font-size: 8.2pt; margin: 6pt 0 2pt; }
        .dato { margin: 0 0 1pt; }
        .etiqueta { font-weight: bold; }
        .equipo { margin-bottom: 4pt; }
        .evaluador { font-size: 6.8pt; font-weight: bold; text-align: center; margin: 9pt 0 3pt; padding-bottom: 2pt; border-bottom: 0.7pt solid #cccccc; }
        .centro { text-align: center; }
        .pie { font-size: 6.8pt; text-align: center; margin-top: 8pt; }
        .pie a { color: #1a56db; font-weight: bold; text-decoration: none; }
        .formato { font-size: 6pt; }
        .qr { text-align: center; margin-top: 6pt; }
        .qr img { width: 52.5pt; height: 52.5pt; }
        .marca-agua { position: fixed; top: 90pt; left: 0; width: 100%; text-align: center; z-index: -1; }
        .marca-agua img { width: 300pt; }
    </style>
</head>
<body>
@php
    $vehiculo = $record->vehicle;
    $aprobacion = $record->fecha_aprobacion ?? $record->updated_at;
    $marca = \App\Support\MarcaCertificado::class;
    $logo = $marca::imagen('logo');
    $onac = $marca::imagen('onac');
    $sello = $marca::imagen('sello');
    // Sin marca de agua cargada se usa el logo, como en el original
    $marcaAgua = $marca::imagen('marca_agua') ?? $logo;
@endphp
    @if ($marcaAgua)
        <div class="marca-agua"><img src="{{ $marcaAgua }}" style="opacity: {{ $marca::opacidad() }};"></div>
    @endif

    <div class="tarjeta">
        @if ($logo || $onac)
            <div class="logos">
                @if ($logo)
                    <img src="{{ $logo }}" alt="IVS">
                @endif
                @if ($onac)
                    <img src="{{ $onac }}" alt="ONAC">
                @endif
            </div>
        @endif

        <h1>Reporte de evaluación No: {{ $record->codigo }}</h1>

        <div class="norma">
            Resoluciones 0957 de 2012, capítulo 5 y 2881, artículo 1 de 2014<br>
            Ministerio de Comercio, Industria, y Turismo
        </div>

        <h2>Información del Servicio</h2>
        <p class="dato"><span class="etiqueta">Taller:</span> {{ $record->user?->name ?? '—' }}</p>
        <p class="dato"><span class="etiqueta">Tipo de servicio:</span> {{ $record->serviceType?->nombre ?? '—' }}</p>
        <p class="dato"><span class="etiqueta">Ciudad:</span> {{ $record->user?->city ?: ($record->owner_ciudad ?? '—') }}</p>
        <p class="dato"><span class="etiqueta">Fecha de expedición:</span> {{ $aprobacion->format('Y-m-d H:i:s') }}</p>
        <p class="dato"><span class="etiqueta">Fecha de vencimiento:</span> {{ ($record->vence_el ?? $aprobacion->copy()->addYear())->format('Y-m-d') }} 00:00:00</p>

        <h2>Detalles del Vehículo</h2>
        <p class="dato"><span class="etiqueta">Placa:</span> {{ strtoupper($vehiculo?->placa ?? $record->vehicle_identification) }}</p>
        <p class="dato"><span class="etiqueta">Marca:</span> {{ strtoupper($vehiculo?->brand?->nombre ?? '—') }}</p>
        <p class="dato"><span class="etiqueta">Año:</span> {{ $vehiculo?->year ?? '—' }}</p>

        <h3>Regulador</h3>
        @forelse ($record->regulators as $regulador)
            <div class="equipo">
                <p class="dato"><span class="etiqueta">Marca:</span> {{ $regulador->brand?->nombre ?? '—' }}</p>
                <p class="dato"><span class="etiqueta">Serial:</span> {{ $regulador->numero_serie }}</p>
            </div>
        @empty
            <p class="dato">No hay reguladores registrados.</p>
        @endforelse

        <h3>Cilindros</h3>
        @forelse ($record->cilindros as $cilindro)
            <div class="equipo">
                <p class="dato"><span class="etiqueta">Marca:</span> {{ $cilindro->brand?->nombre ?? '—' }}</p>
                <p class="dato"><span class="etiqueta">Serial:</span> {{ $cilindro->numero_serie ?? '—' }}</p>
                <p class="dato"><span class="etiqueta">Fabricación:</span> {{ $cilindro->fecha_fabricacion ?? '—' }}</p>
                <p class="dato"><span class="etiqueta">Última prueba:</span> {{ $cilindro->fecha_prueba ?? '—' }}</p>
            </div>
        @empty
            <p class="dato">No hay cilindros registrados.</p>
        @endforelse

        {{-- El certificado se entrega al enviar la lista, antes de la revisión: solo figura el evaluador --}}
        <div class="evaluador">Evaluador</div>
        <p class="dato centro"><span class="etiqueta">Nombre del evaluador:</span> {{ \App\Support\MarcaCertificado::nombreEvaluador($record) }}</p>

        <div class="pie">
            Los términos y condiciones aplicables a esta evaluación se encuentran en la página web:<br>
            <a href="https://www.ivscertificaciones.com/">www.ivscertificaciones.com</a><br>
            <span class="formato">FO-OP-026 V11</span>
        </div>

        <div class="qr">
            <img src="{{ $marca::qr($record->urlVerificacion()) }}" alt="QR">
            @if ($sello)
                <img src="{{ $sello }}" alt="Sello" style="margin-left: 14pt;">
            @endif
            <br><span class="formato">Escanee para verificar este certificado</span>
        </div>
    </div>
</body>
</html>
