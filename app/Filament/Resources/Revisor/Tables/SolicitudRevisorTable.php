<?php

namespace App\Filament\Resources\Revisor\Tables;

use App\Enums\EstadoSolicitud;
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
            ->columns([
                TextColumn::make('vehicle_identification')
                    ->label('Placa')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Solicitud $record) => $record->user?->name),

                TextColumn::make('verificacion.evaluador.name')
                    ->label('Evaluador')
                    ->visibleFrom('md'),

                TextColumn::make('serviceType.nombre')
                    ->label('Servicio')
                    ->visibleFrom('lg'),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                    ->color(fn (?string $state) => EstadoSolicitud::colorDe($state)),

                TextColumn::make('codigo')
                    ->label('Certificado')
                    ->placeholder('—')
                    ->copyable()
                    ->visibleFrom('md'),

                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revisar')
                    ->label(fn (Solicitud $record) => $record->estado === EstadoSolicitud::Evaluada->value ? 'Auditar' : 'Ver')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->color(fn (Solicitud $record) => $record->estado === EstadoSolicitud::Evaluada->value ? 'primary' : 'gray')
                    ->url(fn (Solicitud $record) => SolicitudRevisorResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Solicitud $record) => SolicitudRevisorResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nada por auditar')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
