<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Tables;

use App\Enums\EstadoSolicitud;
use App\Filament\Actions\ExportarSolicitudesAction;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Models\Solicitud;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SolicitudesAbiertasTable
{
    public static function configure(Table $table): Table
    {
        // Solo el evaluador inspecciona; el admin ve en modo consulta
        $puedeEvaluar = fn (Solicitud $record) => $record->esEvaluable() && (bool) Auth::user()?->hasRole('evaluador');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'serviceType', 'vehicle.brand', 'vehicle.model', 'vehicleIdentificationType']))
            // Lo más antiguo primero: se atiende por orden de llegada
            ->defaultSort('updated_at', 'asc')
            // Mismas columnas y orden de «Solicitudes abiertas» en la plataforma anterior:
            // Id | tipo | Identificación | Tipo Servicio | Taller. Lo demás va después.
            ->columns([
                TextColumn::make('id')
                    ->label('Id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehicleIdentificationType.nombre')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => mb_strtoupper((string) $state))
                    ->visibleFrom('lg'),

                TextColumn::make('vehicle_identification')
                    ->label('Identificación')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('serviceType.nombre')
                    ->label('Tipo Servicio')
                    ->visibleFrom('md'),

                TextColumn::make('user.name')
                    ->label('Taller')
                    ->searchable(),

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
            ->headerActions([
                ExportarSolicitudesAction::make('evaluaciones'),
            ])
            ->toolbarActions([
                ExportarSolicitudesAction::seleccionadas('evaluaciones'),
            ])
            ->recordActions([
                Action::make('evaluar')
                    ->label(fn (Solicitud $record) => SolicitudResource::verificacionDe($record) ? 'Continuar' : 'Evaluar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->visible($puedeEvaluar)
                    ->url(fn (Solicitud $record) => SolicitudResource::getUrl('verificacion', ['record' => $record])),

                Action::make('cambiarAnualMasCambio')
                    ->label('Cambiar anual + cambio')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn (Solicitud $record) => $puedeEvaluar($record) && $record->serviceType?->nombre === 'REVISION ANUAL')
                    ->requiresConfirmation()
                    ->modalHeading('¿Está seguro de cambiar el servicio de la solicitud a anual + cambio?')
                    ->modalDescription('La revisión anual pasa a exigir un chip nuevo.')
                    ->action(function (Solicitud $record) {
                        $tipo = \App\Models\ServiceType::where('nombre', 'REVISION ANUAL + CAMBIO')->first();

                        if (! $tipo) {
                            \Filament\Notifications\Notification::make()->title('No fue posible el cambio de servicio')->danger()->send();

                            return;
                        }

                        $record->update(['service_type_id' => $tipo->id]);
                        \Filament\Notifications\Notification::make()->title('Cambio de servicio exitoso')->success()->send();
                    }),

                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Solicitud $record) => ! $puedeEvaluar($record))
                    ->url(fn (Solicitud $record) => SolicitudResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Solicitud $record) => $puedeEvaluar($record)
                ? SolicitudResource::getUrl('verificacion', ['record' => $record])
                : SolicitudResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Sin solicitudes en esta bandeja')
            ->emptyStateDescription('Cuando un taller asignado cargue una solicitud aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
