<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Verificación de certificado | IVS</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #0f172a; }
        .tarjeta { max-width: 460px; margin: 32px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, .12); }
        .franja { height: 8px; background: #cc0000; }
        .cuerpo { padding: 28px 24px 24px; }
        .logo { text-align: center; margin-bottom: 18px; }
        .logo img { height: 56px; }
        h1 { font-size: 20px; text-align: center; margin: 0 0 18px; }
        .estado { text-align: center; font-weight: 700; font-size: 18px; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .vigente { background: #dcfce7; color: #166534; }
        .vencido, .invalido { background: #fee2e2; color: #991b1b; }
        dl { margin: 0; }
        .fila { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .fila:last-child { border-bottom: 0; }
        dt { color: #64748b; }
        dd { margin: 0; font-weight: 600; text-align: right; }
        .pie { text-align: center; font-size: 12px; color: #64748b; padding: 14px 24px 22px; }
    </style>
</head>
<body>
    <div class="tarjeta">
        <div class="franja"></div>
        <div class="cuerpo">
            <div class="logo"><img src="{{ asset('images/logo-sm.png') }}" alt="IVS"></div>
            <h1>Verificación de certificado</h1>

            @if ($solicitud)
                @php($vencido = $solicitud->vence_el && $solicitud->vence_el->endOfDay()->isPast())
                <div class="estado {{ $vencido ? 'vencido' : 'vigente' }}">
                    {{ $vencido ? 'Certificado vencido' : 'Certificado válido y vigente' }}
                </div>

                <dl>
                    <div class="fila"><dt>Certificado N.º</dt><dd>{{ $solicitud->codigo }}</dd></div>
                    <div class="fila"><dt>Placa o chasis</dt><dd>{{ $solicitud->placa() }}</dd></div>
                    <div class="fila"><dt>Tipo de servicio</dt><dd>{{ $solicitud->serviceType?->nombre ?? '—' }}</dd></div>
                    <div class="fila"><dt>Taller</dt><dd>{{ $solicitud->user?->name ?? '—' }}</dd></div>
                    <div class="fila"><dt>Fecha de expedición</dt><dd>{{ $solicitud->fecha_aprobacion?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="fila"><dt>Fecha de vencimiento</dt><dd>{{ $solicitud->vence_el?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
            @else
                <div class="estado invalido">Certificado no encontrado</div>
                <p style="font-size: 14px; text-align: center; color: #475569;">
                    Este código no corresponde a un certificado emitido por IVS. Si lo recibió en un documento, comuníquese con nosotros para confirmarlo.
                </p>
            @endif
        </div>
        <div class="pie">
            IVS Compañía de Certificaciones S.A.S. · <a href="https://www.ivscertificaciones.com" style="color: #cc0000;">www.ivscertificaciones.com</a><br>
            Consulta realizada el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
