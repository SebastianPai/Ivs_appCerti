<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- «Vehiculo Certificado» de la plataforma anterior: lleva el certificado adjunto. Variables: $placa, $detalle --}}
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .webkit { max-width: 540px; background-color: #ffffff; margin: 0 auto; padding: 20px 40px 40px; text-align: center; color: #111827; }
        .cabecera img { height: 80px; vertical-align: middle; margin-right: 40px; }
        .cabecera span { display: inline-block; vertical-align: middle; font-size: 26px; font-weight: 800; line-height: 1.5; }
        h1 { margin: 60px 0; font-size: 26px; font-weight: 700; }
        p { margin: 0 0 22px; font-size: 13px; }
        .pie { margin-top: 70px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="webkit">
        <div class="cabecera">
            <img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS">
            <span>Alertas y<br>Notificaciones</span>
        </div>

        <h1>Certificado</h1>

        <p><strong>¡Gracias por confiar en nosotros!</strong></p>
        <p>Adjunto puedes encontrar el certificado generado.</p>
        <p>Placa {{ $placa }} · {{ $detalle }}</p>

        <div class="pie">IVS COMPAÑIA DE CERTIFICACIONES ©{{ date('Y') }}</div>
    </div>
</body>
</html>
