{{-- Correos de la cuenta (bienvenida/confirmación, contraseña, cambio de correo). Ver App\Notifications\Cuenta.
     Variables: $etiqueta, $titulo, $nombre, $mensaje, $boton_url, $boton_texto, $nota (texto pequeño al final) --}}
@extends('emails.layout', ['mensaje_admin' => null])

@section('etiqueta', $etiqueta)
@section('titulo', $titulo)

@section('contenido')
    @if (filled($nombre ?? null))
        <p style="margin:0 0 10px;">Hola <strong>{{ $nombre }}</strong>,</p>
    @endif
    <p style="margin:0 0 20px; white-space:pre-line;">{{ $mensaje }}</p>
    @include('emails.partes.boton', ['url' => $boton_url, 'texto' => $boton_texto])
    <p style="margin:18px 0 0; font-size:12px; color:#8d8184;">{{ $nota }}</p>
    <p style="margin:10px 0 0; font-size:12px; color:#a69c9e; word-break:break-all;">Si el botón no funciona, copie este enlace en el navegador:<br><a href="{{ $boton_url }}" style="color:#8b3c42;">{{ $boton_url }}</a></p>
@endsection
