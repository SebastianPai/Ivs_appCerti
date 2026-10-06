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
        $pendientes = EstadoSolicitud::Evaluada->value;

        return [
            'pendientes' => Tab::make('Por auditar')
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', $pendientes))
                ->badge(fn () => SolicitudRevisorResource::getEloquentQuery()->where('estado', $pendientes)->count() ?: null)
                ->badgeColor('warning'),

            'devueltas' => Tab::make('Devueltas al evaluador')
                ->icon('heroicon-o-arrow-uturn-left')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoSolicitud::CorreccionTecnica->value)),

            'aprobadas' => Tab::make('Aprobadas')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoSolicitud::Aprobada->value)),
        ];
    }
}
