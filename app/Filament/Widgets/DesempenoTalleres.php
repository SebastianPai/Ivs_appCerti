<?php

namespace App\Filament\Widgets;

use App\Support\Indicadores;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/** Talleres con más solicitudes: aprobadas, devoluciones y certificados por renovar. */
class DesempenoTalleres extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.desempeno-talleres';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    protected function getViewData(): array
    {
        return ['talleres' => Indicadores::desdeFiltros($this->pageFilters)->talleres()];
    }
}
