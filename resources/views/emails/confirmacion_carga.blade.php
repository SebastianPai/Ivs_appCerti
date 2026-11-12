{{-- Al taller: documentos recibidos. Variables: $user_name, $placa, $url_solicitud --}}
@extends('emails.layout')

@section('etiqueta', 'Documentación')
@section('titulo', 'Documentación Recibida')

@section('contenido')
    <p style="margin:0 0 10px;">Hola <strong>{{ $user_name }}</strong>,</p>
    <p style="margin:0 0 18px;">Hemos recibido exitosamente los documentos para la certificación del vehículo. A continuación, los detalles del registro:</p>
    @include('emails.partes.datos', ['filas' => ['Placa del Vehículo' => $placa, 'Fecha y Hora' => now()->format('d/m/Y - h:i A')]])
    @include('emails.partes.boton', ['url' => $url_solicitud, 'texto' => 'Ver Estado'])
@endsection
