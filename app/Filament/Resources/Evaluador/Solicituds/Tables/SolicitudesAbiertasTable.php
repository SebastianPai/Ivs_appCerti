<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Tables;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Models\Solicitud;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SolicitudesAbiertasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'serviceType', 'vehicle.brand', 'vehicle.model']))
            // Lo más antiguo primero: se atiende por orden de llegada
            ->defaultSort('updated_at', 'asc')
            ->columns([
                TextColumn::make('vehicle_identification')
                    ->label('Placa')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Solicitud $record) => $record->user?->name),

                TextColumn::make('user.name')
                    ->label('Taller')
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('serviceType.nombre')
                    ->label('Servicio')
                    ->visibleFrom('lg'),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                    ->color(fn (?string $state) => EstadoSolicitud::colorDe($state)),

                // Lo que hay que atender: corrección del revisor o lo que se le pidió al taller
                TextColumn::make('nota')
                    ->label('Observación')
                    ->state(fn (Solicitud $record) => match ($record->estado) {
                        EstadoSolicitud::CorreccionTecnica->value => 'Revisor: '.$record->observaciones_revisor,
                        EstadoSolicitud::DevueltaTaller->value, EstadoSolicitud::Subsanada->value => $record->observacion_devolucion,
                        default => null,
                    })
                    ->wrap()
                    ->limit(60)
                    ->color(fn (Solicitud $record) => $record->estado === EstadoSolicitud::CorreccionTecnica->value ? 'danger' : null)
                    ->placeholder('—')
                    ->visibleFrom('md'),

                TextColumn::make('updated_at')
                    ->label('Esperando desde')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('evaluar')
                    ->label(fn (Solicitud $record) => SolicitudResource::verificacionDe($record) ? 'Continuar' : 'Evaluar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->visible(fn (Solicitud $record) => $record->esEvaluable())
                    ->url(fn (Solicitud $record) => SolicitudResource::getUrl('verificacion', ['record' => $record])),

                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Solicitud $record) => ! $record->esEvaluable())
                    ->url(fn (Solicitud $record) => SolicitudResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Solicitud $record) => $record->esEvaluable()
                ? SolicitudResource::getUrl('verificacion', ['record' => $record])
                : SolicitudResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Sin solicitudes en esta bandeja')
            ->emptyStateDescription('Cuando un taller asignado cargue una solicitud aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
