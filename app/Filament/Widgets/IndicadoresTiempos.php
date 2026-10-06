<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Support\Indicadores;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tiempos promedio por etapa, devoluciones y alertas del periodo filtrado. */
class IndicadoresTiempos extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Indicadores del periodo';

    public static function canView(): bool
    {
        return Dashboard::veIndicadores();
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $t = Indicadores::desdeFiltros($this->pageFilters)->tiempos();
        $dias = SystemSetting::vigencia()['dias_aviso'];
        $porRenovar = Solicitud::query()->porRenovar($dias)->count();

        return [
            Stat::make('Ciclo completo', Indicadores::duracion($t['ciclo']))
                ->description("Promedio de creación a certificado · {$t['aprobadas']} aprobadas")
                ->descriptionIcon('heroicon-m-clock'),
            Stat::make('Evaluación', Indicadores::duracion($t['evaluacion']))
                ->description('De creación a envío al revisor')
                ->descriptionIcon('heroicon-m-clipboard-document-check'),
            Stat::make('Revisión final', Indicadores::duracion($t['revision']))
                ->description('De envío al revisor a aprobación')
                ->descriptionIcon('heroicon-m-check-badge'),
            Stat::make('Devueltas al taller', $t['tasa_devolucion'] === null ? '—' : "{$t['tasa_devolucion']} %")
                ->description("De {$t['creadas']} solicitudes creadas en el periodo")
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color(($t['tasa_devolucion'] ?? 0) > 30 ? 'warning' : 'gray'),
            Stat::make('Sin movimiento', $t['estancadas'])
                ->description('Solicitudes abiertas con más de 5 días quietas')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($t['estancadas'] > 0 ? 'danger' : 'success'),
            Stat::make('Por renovar', $porRenovar)
                ->description("Certificados que vencen en {$dias} días o ya vencieron")
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($porRenovar > 0 ? 'warning' : 'success'),
        ];
    }
}
