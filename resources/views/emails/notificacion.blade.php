{{-- Plantilla genérica. Variables: $titulo, $saludo, $cuerpo, $placa, $detalle (opcional), $boton_texto, $boton_url --}}
@extends('emails.layout')

@section('titulo', $titulo)

@section('contenido')
    <p style="margin:0 0 10px;">Hola <strong>{{ $saludo }}</strong>,</p>
    <p style="margin:0 0 18px;">{{ $cuerpo }}</p>
    @include('emails.partes.datos', ['filas' => array_filter(['Placa del vehículo' => $placa ?? null, 'Detalle' => $detalle ?? null])])
    @if (! empty($boton_url))
        @include('emails.partes.boton', ['url' => $boton_url, 'texto' => $boton_texto ?? 'Ir a la plataforma'])
    @endif
@endsection
