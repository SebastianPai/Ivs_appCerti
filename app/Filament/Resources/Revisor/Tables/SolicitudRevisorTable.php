<?php

namespace App\Filament\Resources\Revisor\Tables;

use App\Enums\EstadoSolicitud;
use App\Filament\Actions\ExportarSolicitudesAction;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use App\Models\Solicitud;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SolicitudRevisorTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'serviceType', 'verificacion.evaluador']))
            ->defaultSort('updated_at', 'asc')
            // Mismas columnas y orden del listado del revisor en la plataforma anterior:
            // Id | Estado | Placa o Chasis | Nombre Taller | Servicio | Certificado. Lo demás va después.
            ->columns([
                TextColumn::make('id')
                    ->label('Id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                    ->color(fn (?string $state) => EstadoSolicitud::colorDe($state)),

                TextColumn::make('vehicle_identification')
                    ->label('Placa o Chasis')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Nombre Taller')
                    ->searchable(),

                TextColumn::make('serviceType.nombre')
                    ->label('Servicio')
                    ->visibleFrom('md'),

                TextColumn::make('codigo')
                    ->label('Certificado')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('verificacion.evaluador.name')
                    ->label('Evaluador')
                    ->visibleFrom('lg'),

                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                ExportarSolicitudesAction::make('auditoria'),
            ])
            ->toolbarActions([
                ExportarSolicitudesAction::seleccionadas('auditoria'),
            ])
            ->recordActions([
                Action::make('revisar')
                    ->label(fn (Solicitud $record) => $record->estaPendienteDeRevision() ? 'Revisar' : 'Ver')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->color(fn (Solicitud $record) => $record->estaPendienteDeRevision() ? 'primary' : 'gray')
                    ->url(fn (Solicitud $record) => SolicitudRevisorResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Solicitud $record) => SolicitudRevisorResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nada por auditar')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
