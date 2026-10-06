<?php

namespace App\Filament\Resources\Revisor\Schemas;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Solicituds\Schemas\AttachmentForm;
use App\Models\EvaluacionGeolocalizacion;
use App\Models\Solicitud;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class SolicitudRevisorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Expediente')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Vehículo')
                        ->icon('heroicon-m-truck')
                        ->schema([
                            Grid::make(['default' => 2, 'md' => 3, 'xl' => 4])->schema([
                                TextEntry::make('estado')->badge()
                                    ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                                    ->color(fn (?string $state) => EstadoSolicitud::colorDe($state)),
                                TextEntry::make('vehicle_identification')->label('Placa / chasis')->weight('bold'),
                                TextEntry::make('vehicle.brand.nombre')->label('Marca')->placeholder('—'),
                                TextEntry::make('vehicle.model.nombre')->label('Línea')->placeholder('—'),
                                TextEntry::make('vehicle.year')->label('Año')->placeholder('—'),
                                TextEntry::make('vehicle.type.nombre')->label('Clase')->placeholder('—'),
                                TextEntry::make('vehicle.motor')->label('Motor')->placeholder('—'),
                                TextEntry::make('vehicle.fuel_base')->label('Combustible original')->placeholder('—'),
                                TextEntry::make('serviceType.nombre')->label('Servicio')->placeholder('—'),
                                TextEntry::make('combustionSystem.nombre')->label('Sistema')->placeholder('—'),
                                TextEntry::make('applicationType.nombre')->label('Aplicación')->placeholder('—'),
                                TextEntry::make('technology.nombre')->label('Tecnología')->placeholder('—'),
                            ]),
                            Section::make('Propietario')->compact()->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextEntry::make('owner_nombre')->label('Nombre')
                                        ->formatStateUsing(fn (Solicitud $record) => trim($record->owner_nombre.' '.$record->owner_apellido)),
                                    TextEntry::make('owner_document')->label('Documento')
                                        ->formatStateUsing(fn (Solicitud $record) => "{$record->owner_document_type} {$record->owner_document}"),
                                    TextEntry::make('owner_ciudad')->label('Ciudad'),
                                    TextEntry::make('owner_telefono')->label('Teléfono'),
                                    TextEntry::make('owner_email')->label('Correo'),
                                    TextEntry::make('user.name')->label('Taller'),
                                ]),
                            ]),
                        ]),

                    Tab::make('Documentos')
                        ->icon('heroicon-m-paper-clip')
                        ->schema([
                            View::make('filament.revisor.galeria')
                                ->viewData(fn (Solicitud $record) => [
                                    'items' => $record->adjuntos->map(fn ($a) => [
                                        'titulo' => AttachmentForm::etiqueta($a->nombre_adjunto),
                                        'ruta' => $a->ruta_archivo,
                                    ]),
                                ]),
                        ]),

                    Tab::make('Inspección')
                        ->icon('heroicon-m-map-pin')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                TextEntry::make('verificacion.evaluador.name')->label('Evaluador')->placeholder('—'),
                                TextEntry::make('verificacion.chip.codigo')->label('Chip leído')->placeholder('No registrado')->badge()->color('info'),
                                TextEntry::make('verificacion.verificada_en')->label('Enviada el')->dateTime('d/m/Y H:i')->placeholder('—'),
                                IconEntry::make('verificacion.conflicto_interes')->label('Declaró no conflicto de interés')->boolean(),
                                TextEntry::make('verificacion.observaciones')->label('Observaciones del evaluador')->placeholder('Sin observaciones')->columnSpan(['md' => 2]),
                            ]),
                            View::make('filament.revisor.mapa')
                                ->viewData(fn (Solicitud $record) => [
                                    'geo' => EvaluacionGeolocalizacion::where('solicitud_id', $record->id)
                                        ->where('evaluador_id', $record->verificacion?->evaluador_id)
                                        ->first(),
                                ]),
                            Section::make('Fotos de campo')->compact()->schema([
                                View::make('filament.revisor.galeria')
                                    ->viewData(fn (Solicitud $record) => [
                                        'items' => ($record->verificacion?->fotos ?? collect())->map(fn ($f) => [
                                            'titulo' => $f->nombre_foto,
                                            'ruta' => $f->ruta_foto,
                                        ]),
                                    ]),
                            ]),
                        ]),

                    Tab::make('Checklist')
                        ->icon('heroicon-m-clipboard-document-check')
                        ->schema([
                            View::make('filament.revisor.checklist')
                                ->viewData(fn (Solicitud $record) => [
                                    'datos' => $record->verificacion?->datos_checklist ?? [],
                                ]),
                        ]),

                    Tab::make('Equipos')
                        ->icon('heroicon-m-wrench-screwdriver')
                        ->schema([
                            RepeatableEntry::make('cilindros')
                                ->label('Cilindros')
                                ->schema([
                                    TextEntry::make('brand.nombre')->label('Marca'),
                                    TextEntry::make('numero_serie')->label('Serie'),
                                    TextEntry::make('capacidad')->label('Capacidad')->suffix(' L'),
                                    TextEntry::make('fecha_fabricacion')->label('Fabricación')->date('m/Y'),
                                    TextEntry::make('fecha_prueba')->label('Prueba hidrostática')->date('d/m/Y'),
                                ])
                                ->columns(['default' => 2, 'md' => 5]),
                            RepeatableEntry::make('regulators')
                                ->label('Reguladores')
                                ->schema([
                                    TextEntry::make('brand.nombre')->label('Marca'),
                                    TextEntry::make('numero_serie')->label('Serie'),
                                ])
                                ->columns(2),
                        ]),
                ]),
        ]);
    }

    public static function url(?string $ruta): ?string
    {
        return filled($ruta) ? Storage::disk('public')->url($ruta) : null;
    }
}
