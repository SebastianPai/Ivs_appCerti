{{--
    Plantilla común de los correos del flujo (Correo::enviar). Cada correo define:
      @section('etiqueta')   texto corto sobre el título (opcional)
      @section('titulo')     título grande
      @section('contenido')  cuerpo
    Variables comunes que agrega Correo::enviar: $mensaje_admin (texto editable en Configuración → Correos)
    y $pie_correo (firma/contacto editable). Si no llegan, se usa el contacto de siempre.
    Estilos en línea y tablas: así se ve igual en Gmail, Outlook y el celular.
--}}
@php
    $primario = '#a5484e';
    $pie = trim((string) ($pie_correo ?? '')) !== '' ? $pie_correo : "IVS Certificaciones · Calle 8 #47-136 Nueva Tequendama, Cali\n(602) 308 7609 · servicioalcliente@ivscertificaciones.com · gerente@ivscertificaciones.com";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo')</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f3f3; }
        a { color: {{ $primario }}; }
        @media (max-width: 620px) {
            .ivs-tarjeta { border-radius: 0 !important; }
            .ivs-cuerpo { padding: 28px 22px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f4f3f3; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#3d3236;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f3f3;">
        <tr>
            <td align="center" style="padding:32px 12px 40px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="ivs-tarjeta" style="max-width:600px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 12px 30px -18px rgba(80,40,45,.35);">
                    <tr><td style="height:5px; background:{{ $primario }}; font-size:0; line-height:0;">&nbsp;</td></tr>
                    <tr>
                        <td style="padding:26px 36px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;"><img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS Certificaciones" height="38" style="display:block; height:38px; width:auto; border:0;"></td>
                                    <td style="vertical-align:middle; padding-left:10px; font-size:16px; color:#2f2427;"><strong style="color:{{ $primario }};">IVS</strong> Certificaciones</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="ivs-cuerpo" style="padding:28px 36px 34px; font-size:15px; line-height:1.6;">
                            @hasSection('etiqueta')
                                <span style="display:inline-block; margin-bottom:12px; padding:4px 12px; border-radius:999px; background:#f5e6e6; color:#8b3c42; font-size:12px; font-weight:600; letter-spacing:.02em;">@yield('etiqueta')</span>
                            @endif
                            <h1 style="margin:0 0 16px; font-size:22px; line-height:1.3; color:#2f2427;">@yield('titulo')</h1>

                            @if (filled($mensaje_admin ?? null))
                                <div style="margin:0 0 20px; padding:14px 16px; border-radius:12px; background:#fbf4f4; border-left:4px solid {{ $primario }}; white-space:pre-line;">{{ $mensaje_admin }}</div>
                            @endif

                            @yield('contenido')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 36px 24px; background:#faf9f9; border-top:1px solid #f0eaea; font-size:12px; line-height:1.6; color:#8d8184; white-space:pre-line;">{{ $pie }}</td>
                    </tr>
                </table>
                <p style="margin:16px 0 0; font-size:11px; color:#a69c9e;">Este es un mensaje automático de la plataforma IVS Certificaciones. Por favor no responda a este correo.</p>
            </td>
        </tr>
    </table>
</body>
</html>
