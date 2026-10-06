<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\SystemSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSolicituds extends ListRecords
{
    protected static string $resource = SolicitudResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva solicitud')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $contar = fn (array $estados) => SolicitudResource::getEloquentQuery()->whereIn('estado', $estados)->count() ?: null;

        $enProceso = [
            EstadoSolicitud::Pendiente->value,
            EstadoSolicitud::Subsanada->value,
            EstadoSolicitud::Evaluada->value,
            EstadoSolicitud::CorreccionTecnica->value,
        ];
        $corregir = [EstadoSolicitud::DevueltaTaller->value];
        $aprobadas = [EstadoSolicitud::Aprobada->value];

        return [
            'en_proceso' => Tab::make('En proceso')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', $enProceso))
                ->badge(fn () => $contar($enProceso)),

            'requiere_accion' => Tab::make('Para corregir')
                ->icon('heroicon-m-exclamation-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', $corregir))
                ->badge(fn () => $contar($corregir))
                ->badgeColor('danger'),

            'aprobadas' => Tab::make('Aprobadas')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', $aprobadas))
                ->badgeColor('success'),

            'por_renovar' => Tab::make('Por renovar')
                ->icon('heroicon-m-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->porRenovar(SystemSetting::vigencia()['dias_aviso']))
                ->badge(fn () => SolicitudResource::getEloquentQuery()->porRenovar(SystemSetting::vigencia()['dias_aviso'])->count() ?: null)
                ->badgeColor('warning'),

            'todas' => Tab::make('Todas')
                ->icon('heroicon-m-list-bullet'),
        ];
    }

    /** Si hay algo para corregir, abrir directamente esa pestaña. */
    public function getDefaultActiveTab(): string|int|null
    {
        $hayDevueltas = SolicitudResource::getEloquentQuery()
            ->where('estado', EstadoSolicitud::DevueltaTaller->value)
            ->exists();

        return $hayDevueltas ? 'requiere_accion' : 'en_proceso';
    }
}
