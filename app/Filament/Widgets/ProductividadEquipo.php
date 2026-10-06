<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Support\Indicadores;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Desempeño de evaluadores y revisores en el periodo. */
class ProductividadEquipo extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.productividad-equipo';

    public static function canView(): bool
    {
        return Dashboard::veIndicadores();
    }

    protected function getViewData(): array
    {
        $i = Indicadores::desdeFiltros($this->pageFilters);

        return [
            'evaluadores' => $i->evaluadores(),
            'revisores' => $i->revisores(),
        ];
    }
}
