<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Técnico {{ $record->codigo }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; line-height: 1.4; color: #333; }
        .certificado-box { border: 2px solid #cc0000; padding: 20px; min-height: 900px; position: relative; }
        .header { text-align: center; margin-bottom: 15px; }
        .header img { height: 55px; }
        .header h1 { color: #cc0000; font-size: 19px; margin: 8px 0 0; text-transform: uppercase; }
        .resolucion { font-size: 10px; color: #666; background-color: #f5f5f5; padding: 8px; border-left: 4px solid #cc0000; margin-bottom: 18px; }
        h2 { font-size: 12px; font-weight: bold; color: #000; border-bottom: 1px solid #ccc; margin: 14px 0 8px; padding-bottom: 3px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 4px 3px; text-align: left; }
        .tabla th { background-color: #f9f9f9; border-bottom: 1px solid #ccc; font-weight: bold; }
        .tabla td { border-bottom: 1px solid #eee; }
        .label { font-weight: bold; color: #000; }
        .footer { position: absolute; bottom: 20px; left: 20px; right: 20px; text-align: center; }
        .firmas td { width: 50%; text-align: center; padding-top: 35px; }
        .firma { border-top: 1px solid #000; width: 200px; margin: 0 auto 4px; }
        .legal { font-size: 9px; color: #777; margin-top: 12px; }
    </style>
</head>
<body>
@php
    $vehiculo = $record->vehicle;
    $aprobacion = $record->fecha_aprobacion ?? $record->updated_at;
    $evaluador = $record->verificacion?->evaluador;
    $logo = public_path('images/logo-pdf.png');
@endphp
    <div class="certificado-box">
        <div class="header">
            @if (file_exists($logo))
                <img src="{{ $logo }}" alt="IVS">
            @endif
            <h1>Reporte Técnico N.º {{ $record->codigo }}</h1>
        </div>

        <div class="resolucion">
            Resoluciones 0957 de 2012, capítulo 5 y 2881, artículo 1 de 2014<br>
            Ministerio de Comercio, Industria y Turismo
        </div>

        <h2>Información del servicio</h2>
        <table>
            <tr>
                <td width="50%">
                    <span class="label">Taller:</span> {{ $record->user?->name ?? '—' }}<br>
                    <span class="label">Ciudad:</span> {{ $record->owner_ciudad ?? '—' }}<br>
                    <span class="label">Expedición:</span> {{ $aprobacion->format('Y-m-d') }}
                </td>
                <td width="50%">
                    <span class="label">Servicio:</span> {{ $record->serviceType?->nombre ?? '—' }}<br>
                    <span class="label">Sistema:</span> {{ $record->combustionSystem?->nombre ?? '—' }}<br>
                    <span class="label">Vencimiento:</span> {{ ($record->vence_el ?? $aprobacion->copy()->addYear())->format('Y-m-d') }}
                </td>
            </tr>
        </table>

        <h2>Vehículo</h2>
        <table>
            <tr>
                <td width="33%"><span class="label">Placa:</span> {{ strtoupper($vehiculo?->placa ?? $record->vehicle_identification) }}</td>
                <td width="33%"><span class="label">Marca:</span> {{ strtoupper($vehiculo?->brand?->nombre ?? '—') }}</td>
                <td width="33%"><span class="label">Línea:</span> {{ strtoupper($vehiculo?->model?->nombre ?? '—') }}</td>
            </tr>
            <tr>
                <td><span class="label">Año:</span> {{ $vehiculo?->year ?? '—' }}</td>
                <td><span class="label">Clase:</span> {{ $vehiculo?->type?->nombre ?? '—' }}</td>
                <td><span class="label">Chip:</span> {{ $record->verificacion?->chip?->codigo ?? '—' }}</td>
            </tr>
        </table>

        <h2>Reguladores</h2>
        <table class="tabla">
            <tr><th>Marca</th><th>Serial</th></tr>
            @forelse ($record->regulators as $regulador)
                <tr>
                    <td>{{ $regulador->brand?->nombre ?? '—' }}</td>
                    <td>{{ $regulador->numero_serie }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No hay reguladores registrados.</td></tr>
            @endforelse
        </table>

        <h2>Cilindros</h2>
        <table class="tabla">
            <tr><th>Marca</th><th>Serial</th><th>Capacidad</th><th>Fabricación</th><th>Prueba hidrostática</th></tr>
            @forelse ($record->cilindros as $cilindro)
                <tr>
                    <td>{{ $cilindro->brand?->nombre ?? '—' }}</td>
                    <td>{{ $cilindro->numero_serie ?? '—' }}</td>
                    <td>{{ $cilindro->capacidad ? $cilindro->capacidad.' L' : '—' }}</td>
                    <td>{{ $cilindro->fecha_fabricacion ?? '—' }}</td>
                    <td>{{ $cilindro->fecha_prueba ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No hay cilindros registrados.</td></tr>
            @endforelse
        </table>

        <div class="footer">
            <table class="firmas">
                <tr>
                    <td>
                        <div class="firma"></div>
                        <strong>EVALUADOR</strong><br>{{ strtoupper($evaluador?->name ?? '—') }}
                    </td>
                    <td>
                        <div class="firma"></div>
                        <strong>REVISOR</strong><br>{{ strtoupper($record->revisor?->name ?? '—') }}
                    </td>
                </tr>
            </table>

            <div class="legal">
                Los términos y condiciones aplicables a esta evaluación se encuentran en
                <a href="https://www.ivscertificaciones.com" style="color: #cc0000; text-decoration: none;">www.ivscertificaciones.com</a><br>
                CT - F - 11 - V4
            </div>
        </div>
    </div>
</body>
</html>
