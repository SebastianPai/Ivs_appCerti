<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado Taller {{ $certificado->id }}</title>
    {{-- Mismos bloques y textos del certificado de la plataforma anterior (FO-OP-106): certificado + Anexo I --}}
    <style>
        @page { margin: 1.3cm 1.6cm; }
        body { font-family: 'Times', 'Times New Roman', serif; font-style: italic; font-size: 12px; line-height: 1.5; color: #111; }
        .centro { text-align: center; }
        .numero { text-align: right; color: #cc0000; font-weight: bold; font-size: 13px; }
        .encabezado td { vertical-align: middle; }
        .marca { font-family: 'Helvetica', sans-serif; font-style: normal; font-weight: bold; font-size: 26px; line-height: 1.1; color: #333; text-align: center; }
        .marca span { color: #c0272d; }
        .organismo { font-weight: bold; font-size: 15px; margin-top: 6px; }
        .grande { font-size: 27px; margin: 16px 0 0; }
        .titular { font-size: 25px; font-weight: bold; margin: 0; }
        .medio { font-size: 20px; font-weight: bold; margin: 14px 0 0; }
        .dato { font-size: 16px; font-weight: bold; }
        .normas { font-weight: bold; font-size: 12px; margin-top: 22px; }
        .condiciones { font-weight: bold; font-size: 11px; margin: 18px 60px 0; }
        .firma { margin-top: 26px; }
        .firma img { height: 38px; }
        .formato { text-align: right; font-size: 9px; font-weight: bold; margin-top: 6px; }
        .salto { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; }
        .anexo th, .anexo td { border: 1px solid #ddd; text-align: center; padding: 3px 4px; font-size: 10px; height: 13px; }
        .anexo th { background: #f2f2f2; padding: 7px 4px; }
        .marca-agua { position: fixed; top: 24%; left: 12%; width: 76%; text-align: center; z-index: -1; }
        .marca-agua img { width: 100%; }
        .pie { text-align: center; font-size: 10px; color: #555; margin-top: 18px; }
    </style>
</head>
<body>
@php
    $taller = $certificado->taller;
    $nombre = mb_strtoupper((string) $taller?->name);
    $ciudad = mb_strtoupper((string) $taller?->city);
    $organismo = \App\Models\CertificadoTaller::ORGANISMO;
    $marca = \App\Support\MarcaCertificado::class;
    $logo = $marca::imagen('logo');
    $onac = $marca::imagen('onac');
    $sello = $marca::imagen('sello');
    $firma = $marca::imagen('firma');
    $marcaAgua = $marca::imagen('marca_agua');
    $desde = $certificado->fecha_inicio->format('Y-m-d').' 00:00:00';
    $hasta = $certificado->fecha_fin->format('Y-m-d').' 23:59:59';
    $generado = ($certificado->created_at ?? now())->locale('es');
    // Anexo: tres bloques ID | Placa | Fecha por fila; se llena hacia abajo y luego hacia la derecha
    $filasPorPagina = 28;
    $paginas = $vehiculos->values()->chunk($filasPorPagina * 3);
@endphp

    @if ($marcaAgua)
        <div class="marca-agua"><img src="{{ $marcaAgua }}" style="opacity: {{ $marca::opacidad() }};"></div>
    @endif

    <div class="numero">Certificado N° {{ $certificado->id }}</div>

    <table class="encabezado">
        <tr>
            <td width="62%" class="marca"><span>Compañía de</span><br>Certificaciones S.A.S.</td>
            <td width="38%" class="centro">
                @if ($logo)
                    <img src="{{ $logo }}" alt="IVS" style="height: 62px;">
                @endif
                @if ($onac)
                    <img src="{{ $onac }}" alt="ONAC" style="height: 62px;">
                @endif
            </td>
        </tr>
    </table>

    <div class="centro organismo">
        NIT. {{ $organismo['nit'] }}<br>
        {{ $organismo['direccion'] }}<br>
        Teléfono: {{ $organismo['telefono'] }}
    </div>

    <div class="centro">
        <p class="grande">Certifica que:</p>
        <p class="titular">{{ $nombre }}</p>
        Ubicación:<br>
        Municipio: {{ $ciudad }}

        <p class="medio">Razón Social:</p>
        <p class="titular" style="font-size: 21px;">{{ $nombre }}</p>
        <span class="dato">NIT: {{ $taller?->nit }}</span><br>
        <span class="dato">Cantidad de vehículos certificados: {{ $vehiculos->count() }}</span>
    </div>

    <div class="centro normas">
        Certifica los vehículos mencionados en el Anexo I, los cuales fueron inspeccionados, aprobados y cumplen con las disposiciones dadas en:<br>
        Resolución 0957 del 21 de marzo del 2012 del Ministerio de Comercio, Industria y Turismo de Colombia.<br>
        Por la cual se expide el Reglamento Técnico aplicable a talleres, equipos y procesos de conversión a gas natural comprimido para uso vehicular (GNCV). Capítulo V.<br>
        Resolución 2881 del 01 de julio del 2014 del Ministerio de Comercio, Industria y Turismo, articulo 1.
    </div>

    <div class="centro condiciones">
        Esquema de Certificación 6 (ISO/IEC 17067:2013)<br>
        Este certificado esta sujeto a que el titular del mismo mantenga el cumplimiento de los requisitos
        especificados en el documento normativo aplicado y pierde su validez si se reemplaza o modifica alguno
        de los componentes del vehículo certificado que afecten las condiciones en que fue otorgada la certificación.
        <br><br>
        Lo anterior en concordancia con las reglas de certificación IN-OP-001
        <br><br>
        La validez de este certificado debe ser consultado con IVS Compañía de Certificaciones SAS
    </div>

    <div class="centro" style="margin-top: 22px;">
        <strong>Fecha de Aprobación:</strong> {{ $desde }}<br>
        <strong>Fecha de Vencimiento:</strong>{{ $hasta }}
    </div>

    <div class="centro firma">
        @if ($firma)
            <img src="{{ $firma }}" alt="Firma">
        @else
            <span style="display: inline-block; height: 38px;"></span>
        @endif
        @if ($sello)
            <img src="{{ $sello }}" alt="Sello" style="margin-left: 18px;">
        @endif
        <br>
        <strong>{{ mb_strtoupper($marca::firmante()) }}</strong><br>
        {{ mb_strtoupper($marca::cargo()) }}
    </div>

    <div class="formato">{{ $organismo['formato'] }}</div>

    @foreach ($paginas->isEmpty() ? collect([collect()]) : $paginas as $numero => $pagina)
        @php($pagina = $pagina->values())
        <div class="salto">
            @if ($logo)
                <img src="{{ $logo }}" alt="IVS" style="height: 52px;">
            @endif

            <p>Santiago de Cali, {{ $generado->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>

            <p>
                Señores<br>
                {{ $nombre }}<br>
                {{ $ciudad }}
            </p>

            <p>RELACION DE VEHICULOS CERTIFICADOS (ANUALES) PERIODO COMPRENDIDO {{ $desde }} AL {{ $hasta }}:</p>

            <table class="anexo">
                <thead>
                    <tr>
                        @for ($bloque = 0; $bloque < 3; $bloque++)
                            <th width="10%">ID</th><th width="10%">Placa</th><th>Fecha</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @for ($fila = 0; $fila < $filasPorPagina; $fila++)
                        <tr>
                            @for ($bloque = 0; $bloque < 3; $bloque++)
                                @php($solicitud = $pagina->get($bloque * $filasPorPagina + $fila))
                                <td>{{ $solicitud?->codigo }}</td>
                                <td>{{ $solicitud?->placa() }}</td>
                                <td>{{ $solicitud?->fecha_aprobacion?->format('Y-m-d H:i:s') }}</td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="pie">
                {{ $loop->iteration }}<br>
                Calle 8 #47-136 Nueva Tequendama<br>
                Cali – Valle Tel: (032) 308 7609<br>
                www.ivscertificaciones.com
            </div>
        </div>
    @endforeach
</body>
</html>
