{{-- Al taller: solicitud creada, falta cargar documentos. Variables: $user_name, $placa, $url_adjuntos --}}
@extends('emails.layout')

@section('etiqueta', 'Nueva solicitud')
@section('titulo', '¡Solicitud Iniciada!')

@section('contenido')
    <p style="margin:0 0 10px;">Hola <strong>{{ $user_name }}</strong>,</p>
    <p style="margin:0 0 18px;">Has creado exitosamente una nueva solicitud de certificación. El siguiente paso es cargar la documentación requerida.</p>
    @include('emails.partes.datos', ['filas' => ['Placa del Vehículo' => $placa, 'Estado Actual' => ['Pendiente de Documentos', '#b45309']]])
    <p style="margin:0 0 10px;">Puedes continuar el proceso ahora mismo haciendo clic en el siguiente botón:</p>
    @include('emails.partes.boton', ['url' => $url_adjuntos, 'texto' => 'Cargar Documentos'])
@endsection
