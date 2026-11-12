{{-- Al taller: solicitud devuelta con el motivo. Variables: $user_name, $observacion, $placa, $url_gestion --}}
@extends('emails.layout')

@section('etiqueta', 'Requiere correcciones')
@section('titulo', 'Solicitud Devuelta')

@section('contenido')
    <p style="margin:0 0 10px;">Hola <strong>{{ $user_name }}</strong>,</p>
    <p style="margin:0 0 18px;">Tu solicitud ha sido revisada por nuestro equipo y requiere correcciones antes de continuar.</p>
    <div style="margin:0 0 20px; padding:14px 16px; border-radius:12px; background:#fff7ed; border:1px solid #fed7aa;">
        <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:700; color:#9a3412; text-transform:uppercase; letter-spacing:.05em;">Motivo de la devolución</span>
        <div style="white-space:pre-line; color:#431407;">{{ $observacion }}</div>
    </div>
    @include('emails.partes.datos', ['filas' => ['Placa del Vehículo' => $placa, 'Acción Requerida' => ['Corregir Documentos', '#a5484e']]])
    <p style="margin:0 0 10px;">Por favor, ingresa a la plataforma para subsanar estas novedades:</p>
    @include('emails.partes.boton', ['url' => $url_gestion, 'texto' => 'Corregir Solicitud'])
@endsection
