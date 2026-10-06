<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSolicitudesAbiertas extends ListRecords
{
    protected static string $resource = SolicitudResource::class;

    public function getTabs(): array
    {
        $tab = function (string $titulo, string $icono, array $estados, ?string $color = null): Tab {
            return Tab::make($titulo)
                ->icon($icono)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', $estados))
                ->badge(fn () => SolicitudResource::getEloquentQuery()->whereIn('estado', $estados)->count() ?: null)
                ->badgeColor($color ?? 'gray');
        };

        return [
            'por_evaluar' => $tab('Por evaluar', 'heroicon-o-clipboard-document-check', EstadoSolicitud::evaluables(), 'warning'),
            'en_taller' => $tab('Esperando taller', 'heroicon-o-clock', [EstadoSolicitud::DevueltaTaller->value]),
            'en_revision' => $tab('En revisión', 'heroicon-o-eye', [EstadoSolicitud::Evaluada->value], 'info'),
            'aprobadas' => Tab::make('Aprobadas')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoSolicitud::Aprobada->value)),
        ];
    }
}
