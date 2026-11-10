<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- «Notificación Nuevo Inspector» de la plataforma anterior. Variables: $saludo, $inspector, $asignado --}}
    <style>
        body { margin: 0; padding: 0; background-color: #cccccc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .webkit { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-top: 80px solid #ee5253; border-bottom: 80px solid #ee5253; }
        .content { padding: 20px 40px 30px; color: #1f2937; line-height: 1.6; font-size: 14px; text-align: center; }
        .marca { text-align: left; font-size: 24px; font-weight: 700; color: #ee5253; }
        .marca img { height: 48px; vertical-align: middle; margin-right: 12px; }
        h1 { margin: 25px 0 0; font-size: 24px; font-weight: 700; color: #555; }
        h2 { margin: 6px 0 30px; font-size: 21px; font-weight: 700; color: #555; }
    </style>
</head>
<body>
    <div class="webkit">
        <div class="content">
            <div class="marca">
                <img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS">IVS COMPAÑIA DE CERTIFICACIONES
            </div>

            <h1>NOTIFICACIÓN</h1>
            <h2>Hola, {{ $saludo }}!</h2>

            <p>
                Se comunica que a partir de la recepción de esta notificación se gestiona la programación para los
                servicios de evaluación en los siguientes servicios:
                <strong>conversiones (iniciales), revisiones periódicas y reposiciones de chip</strong> en instalación
                de componentes para vehículos convertidos a GNCV, como se relaciona a continuación:
            </p>

            <p style="margin-top: 50px;">Inspector {{ $asignado ? 'asignado' : 'desvinculado' }}: <strong>{{ $inspector }}</strong></p>
        </div>
    </div>
</body>
</html>
