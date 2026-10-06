<?php

namespace App\Filament\Resources\Solicituds\Schemas;

use App\Enums\EstadoSolicitud;
use App\Models\Solicitud;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\SystemSetting;
use App\Support\Archivo;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Auth;

class SolicitudInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Estado')
                ->icon('heroicon-o-flag')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 4])->schema([
                        TextEntry::make('estado')
                            ->label('Estado actual')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                            ->color(fn (?string $state) => EstadoSolicitud::colorDe($state)),
                        TextEntry::make('codigo')->label('N.º de certificado')->placeholder('Se asigna al aprobar')->copyable(),
                        TextEntry::make('fecha_aprobacion')->label('Aprobada el')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('vence_el')->label('Vigente hasta')->date('d/m/Y')->placeholder('—')
                            ->color(fn (Solicitud $record) => match (true) {
                                $record->estaVencida() => 'danger',
                                ($record->diasParaVencer() ?? 999) <= SystemSetting::vigencia()['dias_aviso'] => 'warning',
                                default => null,
                            })
                            ->helperText(fn (Solicitud $record) => $record->estaVencida() ? 'Certificado vencido' : null),
                    ]),
                    TextEntry::make('observacion_devolucion')
                        ->label('Correcciones solicitadas por el evaluador')
                        ->color('danger')
                        ->visible(fn (Solicitud $record) => $record->estado === EstadoSolicitud::DevueltaTaller->value && filled($record->observacion_devolucion)),
                ]),

            Section::make('Vehículo y servicio')
                ->icon('heroicon-o-truck')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                        TextEntry::make('vehicle_identification')->label('Placa / chasis')->weight('bold'),
                        TextEntry::make('vehicle.brand.nombre')->label('Marca')->placeholder('—'),
                        TextEntry::make('vehicle.model.nombre')->label('Línea')->placeholder('—'),
                        TextEntry::make('vehicle.year')->label('Año')->placeholder('—'),
                        TextEntry::make('serviceType.nombre')->label('Servicio')->placeholder('—'),
                        TextEntry::make('combustionSystem.nombre')->label('Sistema de combustión')->placeholder('—'),
                        TextEntry::make('applicationType.nombre')->label('Aplicación')->placeholder('—'),
                        TextEntry::make('technology.nombre')->label('Tecnología')->placeholder('—'),
                    ]),
                ]),

            Section::make('Propietario')
                ->icon('heroicon-o-user')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                        TextEntry::make('owner_nombre')->label('Nombre')
                            ->formatStateUsing(fn (Solicitud $record) => trim($record->owner_nombre.' '.$record->owner_apellido)),
                        TextEntry::make('owner_document')->label('Documento')
                            ->formatStateUsing(fn (Solicitud $record) => "{$record->owner_document_type} {$record->owner_document}"),
                        TextEntry::make('owner_telefono')->label('Teléfono')->url(fn ($state) => $state ? "tel:{$state}" : null),
                        TextEntry::make('owner_email')->label('Correo'),
                        TextEntry::make('owner_ciudad')->label('Ciudad')
                            ->formatStateUsing(fn (Solicitud $record) => trim("{$record->owner_ciudad}, {$record->owner_departamento}", ', ')),
                        TextEntry::make('owner_direccion')->label('Dirección'),
                    ]),
                ]),

            Section::make('Equipos instalados')
                ->icon('heroicon-o-beaker')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('regulators')
                        ->label('Reguladores')
                        ->schema([
                            TextEntry::make('brand.nombre')->label('Marca'),
                            TextEntry::make('numero_serie')->label('Serie'),
                        ])
                        ->columns(2),
                    RepeatableEntry::make('cilindros')
                        ->label('Cilindros')
                        ->schema([
                            TextEntry::make('brand.nombre')->label('Marca'),
                            TextEntry::make('numero_serie')->label('Serie'),
                            TextEntry::make('capacidad')->label('Capacidad')->suffix(' L'),
                            TextEntry::make('fecha_prueba')->label('Prueba hidrostática')->date('d/m/Y'),
                        ])
                        ->columns(['default' => 2, 'md' => 4]),
                ]),

            Section::make('Documentos cargados')
                ->icon('heroicon-o-paper-clip')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('adjuntos')
                        ->hiddenLabel()
                        ->placeholder('Aún no ha cargado documentos.')
                        ->schema([
                            TextEntry::make('nombre_adjunto')
                                ->hiddenLabel()
                                ->icon(fn ($record) => $record->ruta_archivo ? 'heroicon-o-document-check' : 'heroicon-o-x-circle')
                                ->iconColor(fn ($record) => $record->ruta_archivo ? 'success' : 'danger')
                                ->url(fn ($record) => Archivo::url($record->ruta_archivo), shouldOpenInNewTab: true),
                        ])
                        ->grid(['default' => 1, 'md' => 2]),
                ]),

            Section::make('Historial')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    View::make('filament.components.linea-tiempo')
                        ->viewData(fn (Solicitud $record) => ['actividades' => $record->historialPara(Auth::user())]),
                ]),
        ]);
    }
}
