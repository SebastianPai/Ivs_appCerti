{{-- «Vehiculo Certificado» de la plataforma anterior: lleva el certificado adjunto. Variables: $placa, $detalle --}}
@extends('emails.layout')

@section('etiqueta', 'Alertas y Notificaciones')
@section('titulo', 'Certificado')

@section('contenido')
    <p style="margin:0 0 8px;"><strong>¡Gracias por confiar en nosotros!</strong></p>
    <p style="margin:0 0 18px;">Adjunto puedes encontrar el certificado generado.</p>
    @include('emails.partes.datos', ['filas' => array_filter(['Placa' => $placa, 'Detalle' => $detalle ?? null])])
@endsection
