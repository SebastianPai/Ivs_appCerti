<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio. Cada rol ve su resumen de pendientes; el admin y el revisor además ven
 * indicadores del periodo elegido (tiempos, productividad, devoluciones, vencimientos).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    // Primera entrada de la barra, con el nombre que tenía en la plataforma anterior
    protected static ?string $navigationLabel = 'Menú';

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

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periodo de los indicadores')
                ->icon('heroicon-o-calendar-days')
                ->compact()
                ->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2])
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
        ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return parent::getFiltersFormContentComponent()->visible(static::veIndicadores());
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
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
