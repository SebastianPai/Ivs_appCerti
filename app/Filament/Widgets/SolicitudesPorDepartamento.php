<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Support\Indicadores;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SolicitudesPorDepartamento extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Solicitudes por departamento';

    protected ?string $description = 'Los 10 con más solicitudes en el periodo';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return Dashboard::veIndicadores();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $datos = Indicadores::desdeFiltros($this->pageFilters)->porDepartamento();

        return [
            'labels' => $datos->keys()->all(),
            'datasets' => [[
                'label' => 'Solicitudes',
                'data' => $datos->values()->all(),
                'backgroundColor' => '#2a78d6',
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(127,127,127,0.15)']],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
