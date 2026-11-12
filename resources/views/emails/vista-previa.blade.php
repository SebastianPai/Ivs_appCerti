{{-- Vista previa de los correos (solo admin). Ver VistaPreviaCorreosController. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista previa de correos · IVS Certificaciones</title>
    <style>
        body { margin: 0; background: #f4f3f3; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color: #2f2427; }
        header { padding: 24px 16px 8px; max-width: 980px; margin: 0 auto; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        header p { margin: 0; color: #7a6e71; font-size: 14px; }
        main { max-width: 980px; margin: 0 auto; padding: 16px; display: grid; gap: 20px; }
        section { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px -22px rgba(80,40,45,.4); }
        .cabeza { padding: 14px 18px; border-bottom: 1px solid #f0eaea; }
        .cabeza b { display: block; font-size: 15px; }
        .cabeza span { font-size: 13px; color: #7a6e71; }
        iframe { display: block; width: 100%; height: 640px; border: 0; background: #f4f3f3; }
    </style>
</head>
<body>
    <header>
        <h1>Vista previa de los correos</h1>
        <p>Datos de ejemplo con los textos guardados en Configuración. No se envía nada.</p>
    </header>
    <main>
        @foreach ($tipos as $tipo => $nombre)
            <section>
                <div class="cabeza">
                    <b>{{ $nombre }}</b>
                    @if (filled($descripciones[$tipo] ?? null))<span style="display:block">{{ $descripciones[$tipo] }}</span>@endif
                    <span>Asunto: {{ \App\Http\Controllers\VistaPreviaCorreosController::asunto($tipo) }}</span>
                </div>
                <iframe src="{{ route('correos.vista-previa', ['tipo' => $tipo]) }}" loading="lazy" title="{{ $nombre }}"></iframe>
            </section>
        @endforeach
    </main>
</body>
</html>
