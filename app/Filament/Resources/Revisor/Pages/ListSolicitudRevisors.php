<?php

namespace App\Filament\Resources\Revisor\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSolicitudRevisors extends ListRecords
{
    protected static string $resource = SolicitudRevisorResource::class;

    public function getTabs(): array
    {

        return [
            'pendientes' => Tab::make('Pendiente Revisar')
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendientesDeRevision())
                ->badge(fn () => SolicitudRevisorResource::getEloquentQuery()->pendientesDeRevision()->count() ?: null)
                ->badgeColor('warning'),

            'devueltas' => Tab::make('Devueltas al evaluador')
                ->icon('heroicon-o-arrow-uturn-left')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoSolicitud::CorreccionTecnica->value)),

            'aprobadas' => Tab::make('Revisadas')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoSolicitud::Aprobada->value)->whereNotNull('revisada_en')),
        ];
    }
}
