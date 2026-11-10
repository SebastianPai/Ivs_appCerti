<?php

namespace App\Filament\Pages;

use App\Models\Chip;
use App\Services\ChipValidationService;
use App\Services\SicomAbierto;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Evaluador › Diagnóstico Chip: se lee un chip y se muestra lo que se sabe de él, en este sistema
 * y en los datos abiertos de MinEnergía (vehículos con gas natural), que se consultan por IDROM.
 */
class DiagnosticoChip extends Page
{
    protected string $view = 'filament.pages.diagnostico-chip';

    protected static ?string $slug = 'diagnostico-chip';

    protected static ?string $title = 'Diagnóstico de Chip';

    protected static ?string $navigationLabel = 'Diagnóstico Chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Evaluador';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public ?array $data = [];

    /** @var array<string, array<string, ?string>>|null */
    public ?array $resultado = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['evaluador', 'admin']);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('chip_codigo')
                    ->label('Id Chip')
                    ->placeholder('Id Chip')
                    ->required()
                    ->maxLength(100)
                    ->autocomplete(false)
                    ->extraInputAttributes(['id' => 'ivs-chip-input', 'style' => 'text-transform: uppercase', 'autocapitalize' => 'characters']),
                View::make('filament.evaluador.solicituds.chip-reader'),
            ])
            ->statePath('data');
    }

    public function consultar(): void
    {
        $codigo = ChipValidationService::normalizar($this->form->getState()['chip_codigo']);
        $chip = Chip::whereRaw('UPPER(codigo) = ?', [$codigo])->with(['verificaciones' => fn ($q) => $q->latest()->with('solicitud.user', 'solicitud.serviceType', 'solicitud.vehicle.brand', 'solicitud.vehicle.model')])->first();
        $ultima = $chip?->verificaciones->first()?->solicitud;

        $this->resultado = [
            'En este sistema' => $chip ? [
                'Chip' => $chip->codigo,
                'Estado' => $chip->activo ? 'Activo' : 'Inactivo',
                'Placa' => $ultima?->placa() ?? 'Sin solicitud',
                'Marca' => $ultima?->vehicle?->brand?->nombre,
                'Modelo' => $ultima?->vehicle?->model?->nombre,
                'Tipo Servicio' => $ultima?->serviceType?->nombre,
                'Taller' => $ultima?->user?->name,
                'Fecha Certificación' => $ultima?->fecha_aprobacion?->format('d/m/Y'),
                'Próxima Revisión' => $ultima?->vence_el?->format('d/m/Y'),
            ] : ['Chip' => $codigo, 'Estado' => 'No está registrado en el inventario de chips'],
            ...(\App\Models\SystemSetting::enabledOr('sicom_consulta_diagnostico', true)
                ? ['Datos abiertos de MinEnergía' => $this->datosAbiertos($codigo)]
                : []),
        ];
    }

    /** @return array<string, ?string> */
    private function datosAbiertos(string $codigo): array
    {
        try {
            $fila = SicomAbierto::porChip($codigo);
        } catch (\Throwable) {
            return ['Estado' => 'No fue posible consultar en este momento. Intente de nuevo.'];
        }

        if (! $fila) {
            return ['Estado' => 'El número de chip no existe en el SICOM'];
        }

        return [
            'Estado' => $fila['estado'] ?? null,
            'Placa' => $fila['placa'] ?? null,
            'VIN' => $fila['vin'] ?? null,
            'Fecha de instalación' => filled($fila['fecha_instalacion'] ?? null) ? date('d/m/Y', strtotime($fila['fecha_instalacion'])) : null,
            'Municipio' => SicomAbierto::municipio($fila),
        ];
    }
}
