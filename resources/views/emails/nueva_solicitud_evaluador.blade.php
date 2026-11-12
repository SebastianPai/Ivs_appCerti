{{-- Al evaluador: un taller asignado creó una solicitud. Variables: $evaluador_name, $cliente_name, $placa, $url_gestion --}}
@extends('emails.layout')

@section('etiqueta', 'Nueva Asignación')
@section('titulo', 'Hola, '.$evaluador_name)

@section('contenido')
    <p style="margin:0 0 18px;">Uno de tus clientes asignados ha generado una nueva solicitud en la plataforma.</p>
    @include('emails.partes.datos', ['filas' => ['Cliente / Taller' => $cliente_name, 'Placa del Vehículo' => $placa, 'Fecha de Solicitud' => now()->format('d/m/Y H:i')]])
    @include('emails.partes.boton', ['url' => $url_gestion, 'texto' => 'Gestionar Solicitud'])
@endsection
