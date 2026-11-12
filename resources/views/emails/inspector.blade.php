{{-- «Notificación Nuevo Inspector» de la plataforma anterior. Variables: $saludo, $inspector, $asignado --}}
@extends('emails.layout')

@section('etiqueta', 'IVS Compañía de Certificaciones')
@section('titulo', 'Notificación')

@section('contenido')
    <p style="margin:0 0 14px; font-size:17px;"><strong>Hola, {{ $saludo }}!</strong></p>
    <p style="margin:0 0 18px;">
        Se comunica que a partir de la recepción de esta notificación se gestiona la programación para los
        servicios de evaluación en los siguientes servicios:
        <strong>conversiones (iniciales), revisiones periódicas y reposiciones de chip</strong> en instalación
        de componentes para vehículos convertidos a GNCV, como se relaciona a continuación:
    </p>
    <p style="margin:24px 0 0; padding:14px 16px; border-radius:12px; background:#f8f6f6; border:1px solid #efe9e9;">Inspector {{ $asignado ? 'asignado' : 'desvinculado' }}: <strong>{{ $inspector }}</strong></p>
@endsection
