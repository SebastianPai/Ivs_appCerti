{{-- Al revisor: evaluación lista para aprobación final. Variables: $evaluador_name, $placa, $url_gestion --}}
@extends('emails.layout')

@section('etiqueta', 'Pendiente de Revisión')
@section('titulo', 'Hola, Revisor')

@section('contenido')
    <p style="margin:0 0 18px;">El evaluador <strong>{{ $evaluador_name }}</strong> ha completado la inspección técnica y ha enviado la solicitud para tu aprobación final.</p>
    @include('emails.partes.datos', ['filas' => ['Placa del Vehículo' => $placa, 'Evaluador Responsable' => $evaluador_name, 'Fecha de Envío' => now()->format('d/m/Y H:i')]])
    @include('emails.partes.boton', ['url' => $url_gestion, 'texto' => 'Revisar Solicitud'])
@endsection
