{{-- Al evaluador: el taller cargó o corrigió los documentos. Variables: $evaluador_name, $cliente_name, $placa, $url_gestion --}}
@extends('emails.layout')

@section('etiqueta', 'Documentación Lista')
@section('titulo', 'Atención, '.$evaluador_name)

@section('contenido')
    <p style="margin:0 0 18px;">El taller <strong>{{ $cliente_name }}</strong> ha cargado los documentos requeridos. La solicitud está lista para tu revisión.</p>
    @include('emails.partes.datos', ['filas' => ['Placa del Vehículo' => $placa, 'Acción Requerida' => ['Revisar Documentos', '#a5484e']]])
    @include('emails.partes.boton', ['url' => $url_gestion, 'texto' => 'Ir a Revisar Documentos'])
@endsection
