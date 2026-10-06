<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorResource;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use App\Filament\Resources\Solicituds\SolicitudResource as TallerResource;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Tablero de inicio: muestra a cada rol lo que tiene pendiente. */
class ResumenSolicitudes extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = Auth::user();

        return match (true) {
            $user->hasRole('cliente') => $this->taller(),
            $user->hasRole('evaluador') => $this->evaluador(),
            $user->hasRole('revisor') => $this->revisor(),
            default => $this->admin(),
        };
    }

    private function taller(): array
    {
        $q = fn () => TallerResource::getEloquentQuery();
        $url = TallerResource::getUrl('index');

        return [
            Stat::make('Para corregir', $q()->where('estado', EstadoSolicitud::DevueltaTaller->value)->count())
                ->description('Solicitudes devueltas por el evaluador')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->url($url.'?activeTab=requiere_accion'),
            Stat::make('En proceso', $q()->whereNotIn('estado', [EstadoSolicitud::DevueltaTaller->value, EstadoSolicitud::Aprobada->value])->count())
                ->description('En evaluación o revisión')
                ->descriptionIcon('heroicon-m-clock')
                ->url($url),
            Stat::make('Aprobadas', $q()->where('estado', EstadoSolicitud::Aprobada->value)->count())
                ->description('Certificados disponibles')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($url.'?activeTab=aprobadas'),
            Stat::make('Por renovar', $q()->porRenovar(SystemSetting::vigencia()['dias_aviso'])->count())
                ->description('Certificados que vencen pronto o ya vencieron')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning')
                ->url($url.'?activeTab=por_renovar'),
        ];
    }

    private function evaluador(): array
    {
        $q = fn () => EvaluadorResource::getEloquentQuery();
        $url = EvaluadorResource::getUrl('index');

        return [
            Stat::make('Por evaluar', $q()->whereIn('estado', EstadoSolicitud::evaluables())->count())
                ->description('Incluye correcciones del revisor')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->url($url),
            Stat::make('Esperando al taller', $q()->where('estado', EstadoSolicitud::DevueltaTaller->value)->count())
                ->descriptionIcon('heroicon-m-clock')
                ->url($url.'?activeTab=en_taller'),
            Stat::make('En revisión', $q()->where('estado', EstadoSolicitud::Evaluada->value)->count())
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->url($url.'?activeTab=en_revision'),
        ];
    }

    private function revisor(): array
    {
        $q = fn () => SolicitudRevisorResource::getEloquentQuery();
        $url = SolicitudRevisorResource::getUrl('index');

        return [
            Stat::make('Por auditar', $q()->where('estado', EstadoSolicitud::Evaluada->value)->count())
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('warning')
                ->url($url),
            Stat::make('Aprobadas este mes', $q()->where('estado', EstadoSolicitud::Aprobada->value)->where('fecha_aprobacion', '>=', now()->startOfMonth())->count())
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($url.'?activeTab=aprobadas'),
        ];
    }

    private function admin(): array
    {
        $porEstado = Solicitud::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect(EstadoSolicitud::cases())
            ->map(fn (EstadoSolicitud $e) => Stat::make($e->label(), $porEstado[$e->value] ?? 0)->color($e->color()))
            ->all();
    }
}
