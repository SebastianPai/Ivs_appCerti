<?php

namespace App\Filament\Pages;

use App\Enums\EstadoSolicitud;
use App\Exports\Exportables as Exportador;
use App\Models\Actividad;
use App\Models\ServiceType;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Exportables de formato fijo (revisor, SICOM, IVS y operaciones). Cada rol ve solo los suyos.
 * El contenido de cada archivo está en App\Exports\Exportables.
 */
class Exportables extends Page
{
    /** Tope del rango de fechas, para no volcar la base completa en un archivo. */
    public const MAX_DIAS = 366;

    protected string $view = 'filament.pages.exportables';

    protected static ?string $slug = 'exportables';

    protected static ?string $title = 'Exportables';

    // Se entra por las entradas del menú de cada rol (ver AdminPanelProvider), como en la plataforma anterior
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    public ?array $data = [];

    /** Resultado de «Filtrar»: encabezados, filas que se muestran y total encontrado. */
    public ?array $vista = null;

    /** Tope de filas en pantalla; el archivo siempre trae todas. */
    private const FILAS_EN_PANTALLA = 200;

    public static function canAccess(): bool
    {
        return Exportador::tiposPara(Auth::user()) !== [];
    }

    public function mount(): void
    {
        // Sin exportable en el enlace se abre «Mis Exportados», el de uso diario, si el rol lo tiene
        $tipos = Exportador::tiposPara(Auth::user());
        $tipo = collect([request()->query('tipo'), 'externo'])->first(fn ($t) => isset($tipos[(string) $t]), array_key_first($tipos));

        $this->form->fill([
            'tipo' => $tipo,
            'columnas' => Exportador::ENCABEZADOS[$tipo] ?? [],
            'personalizar' => false,
            'personalizar' => false,
            'desde' => now()->toDateString(),
            'hasta' => now()->toDateString(),
            'tipo_fecha' => 'certificacion',
            'fecha_de' => 'inspeccion',
            'modo' => 'taller',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $es = fn (string ...$tipos) => fn (Get $get) => in_array($get('tipo'), $tipos, true);

        return $schema
            ->components([
                Section::make('¿Qué desea exportar?')
                    ->icon('heroicon-o-table-cells')
                    ->schema([
                        Select::make('tipo')
                            ->label('Exportable')
                            ->options(fn () => Exportador::tiposPara(Auth::user()))
                            ->required()
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('columnas', Exportador::ENCABEZADOS[$state] ?? []))
                            ->helperText(fn (Get $get) => Exportador::TIPOS[$get('tipo')]['ayuda'] ?? null),
                    ]),

                Section::make('Filtros Exportables')
                    ->icon('heroicon-o-funnel')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            DatePicker::make('desde')
                                ->label('Fecha Inicial')
                                ->native(false)->displayFormat('d/m/Y')
                                ->required(),
                            DatePicker::make('hasta')
                                ->label('Fecha final')
                                ->native(false)->displayFormat('d/m/Y')
                                ->helperText('Se incluye el día completo.')
                                ->required()
                                ->afterOrEqual('desde'),

                            TextInput::make('placa')
                                ->label('Placa o chasis')
                                ->maxLength(20)
                                ->visible($es('revisor', 'operaciones', 'cliente')),
                            Select::make('service_type_id')
                                ->label('Tipo de servicio')
                                ->options(fn () => ServiceType::orderBy('nombre')->pluck('nombre', 'id'))
                                ->placeholder('Todos')
                                ->visible($es('revisor', 'operaciones', 'cliente')),
                            Select::make('estado')
                                ->label('Estado')
                                ->options(fn () => collect(EstadoSolicitud::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                                ->placeholder('Todos')
                                ->visible($es('revisor', 'cliente')),
                            Select::make('certificada')
                                ->label('Certificada')
                                ->options(['si' => 'Certificado', 'no' => 'No certificado'])
                                ->placeholder('Todas')
                                ->visible($es('cliente')),
                            Radio::make('fecha_de')
                                ->label('Las fechas son')
                                ->options(['inspeccion' => 'Por fecha de inspección', 'creacion' => 'Por fecha de creación'])
                                ->inline()
                                ->visible($es('cliente')),
                            Select::make('revisada')
                                ->label('Revisada')
                                ->options(['si' => 'Revisada', 'no' => 'Pendiente Revisar', 'devuelta' => 'Devuelta al evaluador'])
                                ->placeholder('Todas')
                                ->visible($es('revisor')),

                            Radio::make('tipo_fecha')
                                ->label('Las fechas son de')
                                ->options(['certificacion' => 'Certificación', 'creacion' => 'Creación de la solicitud'])
                                ->inline()
                                ->visible($es('ivs')),
                            Radio::make('modo')
                                ->label('Agrupar por')
                                ->options(['taller' => 'Taller', 'inspector' => 'Evaluador'])
                                ->inline()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('usuario_id', null))
                                ->visible($es('ivs')),
                            Select::make('usuario_id')
                                ->label(fn (Get $get) => $get('modo') === 'inspector' ? 'Evaluador' : 'Taller')
                                ->options(fn (Get $get) => User::role($get('modo') === 'inspector' ? 'evaluador' : 'cliente')->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->placeholder('Todos')
                                ->visible($es('ivs')),
                        ]),
                    ]),

                Section::make('Personalizar columnas')
                    ->icon('heroicon-o-view-columns')
                    ->description('El archivo sale con todas las columnas. Active la opción solo si necesita quitar alguna.')
                    ->schema([
                        // Apagado por defecto para que nadie quite una columna por un clic accidental
                        Toggle::make('personalizar')
                            ->label('Quiero elegir las columnas')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => $set('columnas', Exportador::ENCABEZADOS[$get('tipo')] ?? [])),
                        CheckboxList::make('columnas')
                            ->hiddenLabel()
                            ->visible(fn (Get $get) => (bool) $get('personalizar'))
                            ->options(fn (Get $get) => array_combine($columnas = Exportador::ENCABEZADOS[$get('tipo')] ?? [], $columnas))
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->bulkToggleable()
                            ->required()
                            ->validationMessages(['required' => 'Deje al menos una columna marcada.']),
                    ]),
            ])
            ->statePath('data');
    }

    /** «Filtrar»: muestra en pantalla lo mismo que saldría en el archivo, sin descargar nada. */
    public function filtrar(): void
    {
        [$tipo, $filtros, $filas] = $this->consultar();
        $this->vista = null;

        if ($filas === null) {
            return;
        }

        $columnas = ($filtros['personalizar'] ?? false) ? $filtros['columnas'] : Exportador::ENCABEZADOS[$tipo];
        $visibles = array_keys(array_intersect(Exportador::ENCABEZADOS[$tipo], $columnas));

        $this->vista = [
            'encabezados' => array_map(fn (int $i) => Exportador::ENCABEZADOS[$tipo][$i], $visibles),
            'filas' => array_map(fn (array $fila) => array_map(fn (int $i) => $fila[$i], $visibles), array_slice($filas, 0, self::FILAS_EN_PANTALLA)),
            'total' => count($filas),
        ];
    }

    public function limpiar(): void
    {
        $this->vista = null;
        $this->mount();
    }

    /** @return array{0: string, 1: array, 2: array|null} tipo, filtros y filas (null si no hay nada que mostrar) */
    private function consultar(): array
    {
        $filtros = $this->form->getState();
        $tipo = $filtros['tipo'];

        if (Carbon::parse($filtros['desde'])->diffInDays(Carbon::parse($filtros['hasta'])) > self::MAX_DIAS) {
            Notification::make()->title('El rango máximo es de un año')->body('Divida la consulta en rangos más cortos.')->warning()->send();

            return [$tipo, $filtros, null];
        }

        $filas = Exportador::filas($tipo, $filtros, Auth::user());

        if ($filas === []) {
            Notification::make()->title('No se encontraron registros')->body('Ajuste las fechas o los filtros.')->warning()->send();

            return [$tipo, $filtros, null];
        }

        return [$tipo, $filtros, $filas];
    }

    public function exportar()
    {
        [$tipo, $filtros, $filas] = $this->consultar();

        if ($filas === null) {
            return null;
        }

        $desde = Carbon::parse($filtros['desde'])->format('d/m/Y');
        $hasta = Carbon::parse($filtros['hasta'])->format('d/m/Y');
        $columnas = ($filtros['personalizar'] ?? false) ? $filtros['columnas'] : null;
        $ocultas = array_values(array_diff(Exportador::ENCABEZADOS[$tipo], $columnas ?? Exportador::ENCABEZADOS[$tipo]));
        Actividad::registrar(
            'exportacion',
            'Exportable «'.Exportador::TIPOS[$tipo]['etiqueta']."» descargado: {$desde} a {$hasta}, ".count($filas).' fila(s)'
                .($ocultas ? '. Sin las columnas: '.implode(', ', $ocultas) : ''),
        );

        return Exportador::descargar($tipo, $filas, $columnas);
    }
}
