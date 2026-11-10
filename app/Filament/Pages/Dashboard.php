<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use App\Support\Indicadores;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio. Cada rol ve su resumen de pendientes; el admin y el revisor además ven
 * indicadores del periodo elegido (tiempos, productividad, devoluciones, vencimientos).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    // Primera entrada de la barra, con el nombre que tenía en la plataforma anterior
    protected static ?string $navigationLabel = 'Menú';

    protected static ?string $title = 'Menú';

    /** Mismo saludo de la pantalla de inicio de la plataforma anterior. */
    public function getHeading(): string
    {
        return 'Bienvenido, '.Auth::user()?->name;
    }

    public function getSubheading(): ?string
    {
        return 'Encuentra lo que buscas en el menú superior';
    }

    public static function veIndicadores(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'revisor']);
    }

    /** El periodo va en un botón junto al saludo, en vez de un formulario que ocupa la entrada. */
    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label(function (): string {
                    $periodo = Indicadores::desdeFiltros($this->filters);

                    return 'Periodo: '.$periodo->desde->format('d/m/Y').' – '.$periodo->hasta->format('d/m/Y');
                })
                ->icon('heroicon-o-calendar-days')
                ->modalHeading('Periodo de los indicadores')
                ->modalSubmitActionLabel('Aplicar')
                ->visible(static::veIndicadores())
                ->schema([
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->subMonthsNoOverflow(5)->startOfMonth())
                        ->maxDate(fn ($get) => $get('hasta') ?: now()),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now())
                        ->minDate(fn ($get) => $get('desde'))
                        ->maxDate(now()),
                ]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            // Primero lo que tiene a alguien bloqueado: talleres que esperan la fecha de su registro
            Widgets\RegistrosTallerPendientes::class,
            Widgets\ResumenSolicitudes::class,
            Widgets\IndicadoresTiempos::class,
            Widgets\SolicitudesPorMes::class,
            Widgets\SolicitudesPorDepartamento::class,
            Widgets\ProductividadEquipo::class,
            Widgets\DesempenoTalleres::class,
        ];
    }
}
