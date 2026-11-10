<?php

namespace App\Filament\Pages;

use App\Models\Actividad;
use App\Models\CertificadoTaller as Certificado;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Administrador › Certificado Taller: certificado mensual por taller con los vehículos que se le
 * certificaron en el mes. Misma pantalla de la plataforma anterior: filtros, «Vehículos
 * Certificados» (de donde se genera) y «Certificados Generados» (descargar o eliminar).
 */
class CertificadoTaller extends Page implements HasTable
{
    use InteractsWithTable;

    private const ARCHIVO = 'certificadoTaller.pdf';

    protected string $view = 'filament.pages.certificado-taller';

    protected static ?string $slug = 'certificado-taller';

    protected static ?string $title = 'Certificado Taller';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public ?array $data = [];

    /** Tarjeta visible: los certificados ya generados o los vehículos del mes filtrado. */
    public string $vista = 'generados';

    /** Filtros con los que se pulsó el último botón: de aquí salen las dos tablas y el certificado. */
    public array $filtro = [];

    public string $buscar = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public function mount(): void
    {
        // Los certificados se generan a mes vencido
        $mes = now()->subMonthNoOverflow();

        $this->form->fill([
            'desde' => $mes->copy()->startOfMonth()->toDateString(),
            'hasta' => $mes->copy()->endOfMonth()->toDateString(),
            'taller_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros')
                    ->icon('heroicon-o-funnel')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            DatePicker::make('desde')
                                ->label('Fecha Inicial')
                                ->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('hasta')
                                ->label('Fecha de final')
                                ->native(false)->displayFormat('d/m/Y'),
                            Select::make('taller_id')
                                ->label('Taller')
                                ->options(fn () => User::role('cliente')->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->placeholder('Seleccione un taller'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /** El certificado es mensual: del primer al último día de un mismo mes, y de un solo taller. */
    public function verVehiculos(): void
    {
        $filtro = $this->form->getState();

        if (blank($filtro['desde'] ?? null) || blank($filtro['hasta'] ?? null) || blank($filtro['taller_id'] ?? null)) {
            Notification::make()->title('Seleccione las dos fechas y el taller')->warning()->send();

            return;
        }

        $desde = Carbon::parse($filtro['desde']);
        $hasta = Carbon::parse($filtro['hasta']);

        $error = match (true) {
            $desde->day !== 1 => 'Solo se permite el primer día del mes en la fecha inicial',
            ! $hasta->isSameDay($hasta->copy()->endOfMonth()) => 'Solo se permite el último día del mes en la fecha final',
            ! $desde->isSameMonth($hasta) => 'Las dos fechas deben ser del mismo mes',
            default => null,
        };

        if ($error) {
            Notification::make()->title($error)->danger()->send();

            return;
        }

        $this->filtro = $filtro;
        $this->buscar = '';
        $this->vista = 'vehiculos';
        unset($this->vehiculos);
    }

    public function verGenerados(): void
    {
        $this->filtro = $this->form->getState();
        $this->vista = 'generados';
        $this->resetPage();
    }

    #[Computed]
    public function vehiculos(): Collection
    {
        if (blank($this->filtro['taller_id'] ?? null)) {
            return collect();
        }

        return Certificado::vehiculos((int) $this->filtro['taller_id'], $this->filtro['desde'], $this->filtro['hasta'])->get();
    }

    /** Vehículos de la tarjeta, filtrados por el campo «Buscar». */
    public function vehiculosFiltrados(): Collection
    {
        $buscar = mb_strtoupper(trim($this->buscar));

        return $this->vehiculos->when($buscar !== '', fn (Collection $filas) => $filas->filter(
            fn ($solicitud) => str_contains(mb_strtoupper($solicitud->codigo.' '.$solicitud->placa()), $buscar),
        ));
    }

    public function generar()
    {
        if ($this->vista !== 'vehiculos' || $this->vehiculos->isEmpty()) {
            Notification::make()->title('No hay vehículos certificados para generar el certificado')->warning()->send();

            return null;
        }

        // Un solo certificado por taller y mes: si ya existe, se actualiza la cantidad y se descarga
        $periodo = [
            'fecha_inicio' => Carbon::parse($this->filtro['desde'])->toDateString(),
            'fecha_fin' => Carbon::parse($this->filtro['hasta'])->toDateString(),
        ];
        $certificado = Certificado::query()
            ->where('taller_id', $this->filtro['taller_id'])
            ->whereDate('fecha_inicio', $periodo['fecha_inicio'])
            ->whereDate('fecha_fin', $periodo['fecha_fin'])
            ->first() ?? new Certificado(['taller_id' => $this->filtro['taller_id'], ...$periodo]);
        $certificado->generador_id ??= Auth::id();
        $certificado->cantidad = $this->vehiculos->count();
        $nuevo = ! $certificado->exists;
        $certificado->save();

        if ($nuevo) {
            Actividad::registrar('creado', $this->describir($certificado).' generado', $certificado);
        }

        return $this->descargar($certificado);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Certificado::query()
                ->with(['taller:id,name', 'generador:id,name'])
                ->when($this->filtro['taller_id'] ?? null, fn (Builder $q, $taller) => $q->where('taller_id', $taller))
                ->when($this->filtro['desde'] ?? null, fn (Builder $q, $desde) => $q->whereDate('fecha_fin', '>=', $desde))
                ->when($this->filtro['hasta'] ?? null, fn (Builder $q, $hasta) => $q->whereDate('fecha_inicio', '<=', $hasta)))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Id certificado')->searchable()->sortable(),
                TextColumn::make('fecha_inicio')->label('Fecha inicio')->date('d/m/Y')->sortable(),
                TextColumn::make('fecha_fin')->label('Fecha fin')->date('d/m/Y')->sortable(),
                TextColumn::make('cantidad')->label('Cantidad')->sortable(),
                TextColumn::make('taller.name')->label('Taller')->searchable()->sortable(),
                TextColumn::make('generador.name')->label('Generador')->searchable()->placeholder('—'),
                TextColumn::make('created_at')->label('Fecha Generación')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('descargar')
                    ->label('Generar certificado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->tooltip('Generar certificado')
                    ->action(fn (Certificado $record) => $this->descargar($record)),
                Action::make('eliminar')
                    ->label('Eliminar certificado')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Eliminar certificado')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Certificado $record) => "¿Está seguro de eliminar el certificado {$record->id}?")
                    ->modalDescription('Los vehículos y sus certificados individuales no se modifican.')
                    ->action(function (Certificado $record) {
                        Actividad::registrar('eliminado', $this->describir($record).' eliminado', $record);
                        $record->delete();

                        Notification::make()->title('El certificado se eliminó con éxito')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No hay certificados generados')
            ->emptyStateDescription('Filtre un mes y un taller, abra «Vehículos Certificados» y pulse «Generar certificado».');
    }

    private function descargar(Certificado $certificado)
    {
        $pdf = $certificado->pdf()->output();

        return response()->streamDownload(fn () => print($pdf), self::ARCHIVO, ['Content-Type' => 'application/pdf']);
    }

    private function describir(Certificado $certificado): string
    {
        return "Certificado Taller N.º {$certificado->id} de {$certificado->taller?->name} "
            ."({$certificado->fecha_inicio->format('d/m/Y')} a {$certificado->fecha_fin->format('d/m/Y')}, {$certificado->cantidad} vehículo(s))";
    }
}
