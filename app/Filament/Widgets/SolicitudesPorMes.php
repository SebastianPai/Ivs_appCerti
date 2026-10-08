<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Support\Indicadores;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SolicitudesPorMes extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Solicitudes por mes';

    protected ?string $description = 'Creadas frente a certificados emitidos';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return Dashboard::veIndicadores();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $datos = Indicadores::desdeFiltros($this->pageFilters)->porMes();

        // Paleta categórica validada (posiciones 1 y 2): azul y naranja
        $serie = fn (string $nombre, array $valores, string $color) => [
            'label' => $nombre,
            'data' => $valores,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'borderWidth' => 2,
            'pointRadius' => 4,
            'pointHoverRadius' => 6,
            'tension' => 0.25,
        ];

        return [
            'labels' => $datos['meses'],
            'datasets' => [
                $serie('Creadas', $datos['creadas'], '#2a78d6'),
                $serie('Aprobadas', $datos['aprobadas'], '#eb6834'),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(127,127,127,0.15)']],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
