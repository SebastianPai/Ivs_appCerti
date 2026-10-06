<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Plantilla genérica. Variables: $titulo, $saludo, $cuerpo, $placa, $detalle (opcional), $boton_texto, $boton_url --}}
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f6f8; padding: 40px 0 60px; }
        .webkit { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 12px; overflow: hidden; }
        .header { text-align: center; padding: 40px 0 20px; }
        .logo-img { max-width: 150px; height: auto; }
        .content { padding: 0 40px 40px; color: #4b5563; line-height: 1.6; font-size: 16px; }
        h1 { margin: 0 0 15px; font-size: 24px; font-weight: 700; color: #1f2937; text-align: center; }
        p { margin: 0 0 20px; }
        .info-box { background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 25px 0; }
        .info-row { display: block; margin-bottom: 5px; font-size: 13px; color: #6b7280; text-transform: uppercase; font-weight: 600; }
        .info-value { font-weight: 700; color: #111827; font-size: 18px; }
        .detalle { white-space: pre-line; margin-top: 12px; color: #111827; }
        .btn { display: block; width: 240px; margin: 0 auto; padding: 14px 0; background-color: #DC2626; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; text-align: center; }
        .footer { text-align: center; padding-top: 20px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="webkit">
            <div class="header">
                <img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS Certificaciones" class="logo-img">
            </div>
            <div class="content">
                <h1>{{ $titulo }}</h1>
                <p>Hola <strong>{{ $saludo }}</strong>,</p>
                <p>{{ $cuerpo }}</p>

                <div class="info-box">
                    <span class="info-row">Placa del vehículo</span>
                    <span class="info-value">{{ $placa }}</span>
                    @if (! empty($detalle))
                        <div class="detalle">{{ $detalle }}</div>
                    @endif
                </div>

                @if (! empty($boton_url))
                    <a href="{{ $boton_url }}" class="btn">{{ $boton_texto ?? 'Ir a la plataforma' }}</a>
                @endif
            </div>
        </div>
        <div class="footer">IVS Empresa de Certificación S.A.S.</div>
    </div>
</body>
</html>
