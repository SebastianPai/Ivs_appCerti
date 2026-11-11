<?php

namespace App\Filament\Resources\Solicituds\Schemas;

use App\Enums\EstadoSolicitud;
use App\Models\ApplicationType;
use App\Models\CombustionSystem;
use App\Models\CylinderBrand;
use App\Models\RegulatorBrand;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\Technology;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleIdentificationType;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Services\ColombiaGeo;
use App\Services\SicomAbierto;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Formulario de la solicitud del taller.
 * - Crear: asistente por pasos (más cómodo en celular).
 * - Editar: las mismas secciones, todas visibles.
 */
class SolicitudForm
{
    /** Campos del formulario que pertenecen a la tabla vehicles, no a solicituds. */
    public const CAMPOS_VEHICULO = ['brand_id', 'model_id', 'municipio', 'vehicle_type_id', 'year', 'fuel_base'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Mismos bloques y orden de «Nueva Solicitud» en la plataforma anterior
            Section::make('Buscar Vehículo')->icon('heroicon-o-magnifying-glass')->schema(self::vehiculo())->columnSpanFull(),
            Section::make('Tipo de servicio')->icon('heroicon-o-wrench')->schema(self::servicio())->columnSpanFull(),
            Section::make('Propietario')->icon('heroicon-o-user')->schema(self::propietario())->columnSpanFull(),
            Section::make('Datos vehículos')->icon('heroicon-o-truck')->schema(self::datosDelVehiculo())->columnSpanFull(),
            Section::make('Regulador y Cilindros Instalados')->icon('heroicon-o-beaker')->schema(self::equipos())->columnSpanFull(),
        ]);
    }

    /** @return array<Step> */
    public static function steps(): array
    {
        return [
            // Mismos bloques y orden de «Nueva Solicitud» en la plataforma anterior
            Step::make('Buscar Vehículo')->icon('heroicon-o-magnifying-glass')->description('Placa o chasis')->schema(self::vehiculo()),
            Step::make('Tipo de servicio')->icon('heroicon-o-wrench')->description('Servicio y sistema')->schema(self::servicio()),
            Step::make('Propietario')->icon('heroicon-o-user')->description('Datos de contacto')->schema(self::propietario()),
            Step::make('Datos vehículos')->icon('heroicon-o-truck')->description('Marca, modelo y año')->schema(self::datosDelVehiculo()),
            Step::make('Regulador y Cilindros')->icon('heroicon-o-beaker')->description('Equipos instalados')->schema(self::equipos()),
        ];
    }

    private static function grid(array $schema): Grid
    {
        return Grid::make(['default' => 1, 'md' => 2])->schema($schema);
    }

    private static function vehiculo(): array
    {
        return [
            // Solo el admin elige el taller; el taller siempre crea a su nombre
            Select::make('user_id')
                ->label('Taller')
                ->options(fn () => User::role('cliente')->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->visible(fn (string $operation) => $operation === 'create' && (bool) Auth::user()?->hasRole('admin'))
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('service_type_id', null))
                ->helperText('Está creando la solicitud a nombre de este taller.'),

            self::grid([
                Select::make('vehicle_identification_type_id')
                    ->label('Identificar por')
                    ->options(fn () => VehicleIdentificationType::orderBy('id')->pluck('nombre', 'id'))
                    ->default(fn () => VehicleIdentificationType::where('nombre', 'Placa')->value('id'))
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required(),

                TextInput::make('vehicle_identification')
                    ->label(fn (Get $get) => self::esChasis($get) ? 'Número de chasis / VIN' : 'Placa')
                    ->placeholder(fn (Get $get) => self::esChasis($get) ? '9BWZZZ377VT004251' : 'ABC123')
                    ->helperText('Si el vehículo ya fue certificado antes, sus datos se completan solos al salir del campo.')
                    ->required()
                    ->maxLength(30)
                    ->autocomplete(false)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase', 'autocapitalize' => 'characters'])
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(preg_replace('/[\s-]+/', '', $state)) : null)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            $valor = strtoupper(preg_replace('/[\s-]+/', '', (string) $value));

                            if (self::esChasis($get)) {
                                if (! preg_match('/^[A-Z0-9]{5,30}$/', $valor)) {
                                    $fail('El chasis/VIN solo puede tener letras y números (mínimo 5).');
                                }
                            } elseif (! preg_match('/^[A-Z]{3}\d{3}$|^[A-Z]{3}\d{2}[A-Z]$/', $valor)) {
                                $fail('Placa no válida. Formato esperado: ABC123 (carro) o ABC12D (moto).');
                            }
                        },
                        // No permitir dos solicitudes abiertas para el mismo vehículo
                        fn (?Solicitud $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                            $valor = strtoupper(preg_replace('/[\s-]+/', '', (string) $value));
                            $abierta = Solicitud::where('vehicle_identification', $valor)
                                ->where('estado', '!=', EstadoSolicitud::Aprobada->value)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($abierta) {
                                $fail('Ya existe una solicitud en curso para este vehículo.');
                            }
                        },
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set, ?Solicitud $record) => self::autocompletarVehiculo($state, $get, $set, $record === null)),
            ]),
        ];
    }

    /** «Datos vehículos»: va después del propietario, como en la plataforma anterior. */
    private static function datosDelVehiculo(): array
    {
        return [
            self::grid([
                Select::make('brand_id')
                    ->label('Marca')
                    ->options(fn () => VehicleBrand::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('model_id', null))
                    ->required(),

                Select::make('model_id')
                    ->label('Línea / modelo')
                    ->options(fn (Get $get) => $get('brand_id')
                        ? VehicleModel::where('brand_id', $get('brand_id'))->orderBy('nombre')->pluck('nombre', 'id')
                        : [])
                    ->searchable()
                    ->live()
                    ->disabled(fn (Get $get) => ! $get('brand_id'))
                    ->helperText(fn (Get $get) => $get('brand_id') ? '¿No aparece? Créela con el botón +' : 'Primero seleccione la marca')
                    ->createOptionForm([
                        TextInput::make('nombre')->label('Nombre de la línea')->required()->maxLength(80),
                    ])
                    ->createOptionUsing(function (array $data, Get $get) {
                        return VehicleModel::firstOrCreate(
                            ['brand_id' => $get('brand_id'), 'nombre' => strtoupper(trim($data['nombre']))],
                        )->getKey();
                    })
                    ->required(),

                // Va entre el modelo y el año, como en «Datos vehículos» de la plataforma anterior
                Select::make('municipio')
                    ->label('Municipio de la placa')
                    ->options(fn () => ColombiaGeo::municipios())
                    ->searchable()
                    // Obligatorio al crear; al corregir una solicitud vieja que no lo trae no se exige
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Municipio donde está matriculado el vehículo.'),

                Select::make('year')
                    ->label('Año modelo')
                    ->options(function (Get $get) {
                        $modelo = $get('model_id') ? VehicleModel::find($get('model_id')) : null;
                        $inicio = $modelo?->year_start ?? 1980;
                        $fin = $modelo?->year_end ?? (now()->year + 1);

                        return collect(range($fin, $inicio))->mapWithKeys(fn ($y) => [$y => $y])->all();
                    })
                    ->searchable()
                    ->required(),

                Select::make('vehicle_type_id')
                    ->label('Clase de vehículo')
                    ->options(fn () => VehicleType::orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('fuel_base')
                    ->label('Combustible original')
                    ->options(['Gasolina' => 'Gasolina', 'Diesel' => 'Diésel'])
                    ->default('Gasolina')
                    ->required(),
            ]),
        ];
    }

    private static function servicio(): array
    {
        return [
            self::grid([
                Select::make('service_type_id')
                    ->label('Tipo de servicio')
                    // Solo los servicios asignados al taller; al editar se conserva el que ya tenía
                    ->options(fn (Get $get, ?Solicitud $record) => self::serviciosDe($get, $record))
                    ->in(fn (Get $get, ?Solicitud $record) => array_keys(self::serviciosDe($get, $record)))
                    ->validationMessages(['in' => 'Este taller no tiene asignado ese tipo de servicio.'])
                    ->helperText(fn (Get $get, ?Solicitud $record) => self::serviciosDe($get, $record) === []
                        ? (Auth::user()?->hasRole('admin')
                            ? 'Elija el taller. Si ya lo eligió, no tiene servicios asignados: asígnelos con el botón «Asignar servicios».'
                            : 'No tienes permisos a servicios, debes solicitarlos al administrador.')
                        : null)
                    // Atajo del admin: abre en otra pestaña la asignación de servicios del taller elegido,
                    // para no perder lo que ya digitó en la solicitud
                    ->hintAction(
                        \Filament\Actions\Action::make('asignarServiciosTaller')
                            ->label('Asignar servicios')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(fn (Get $get, ?Solicitud $record) => Auth::user()?->hasRole('admin')
                                && filled($get('user_id'))
                                && self::serviciosDe($get, $record) === [])
                            ->url(fn (Get $get) => \App\Filament\Resources\Users\UserResource::getUrl('index', [
                                'tableAction' => 'asignarServicios',
                                'tableActionRecord' => $get('user_id'),
                            ]), shouldOpenInNewTab: true)
                    )
                    ->required(),

                Select::make('combustion_system_id')
                    ->label('Sistema de combustión')
                    ->options(fn () => CombustionSystem::orderBy('id')->pluck('nombre', 'id'))
                    ->required(),

                Select::make('application_type_id')
                    ->label('Tipo de aplicación')
                    ->options(fn () => ApplicationType::orderBy('id')->pluck('nombre', 'id'))
                    ->required(),

                Select::make('technology_id')
                    ->label('Tecnología')
                    ->options(fn () => Technology::orderBy('id')->pluck('nombre', 'id'))
                    ->required(),

                DatePicker::make('fecha_instalacion')
                    ->label('Fecha de instalación del sistema de gas')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->helperText('Opcional. Si se deja vacía, se toma la fecha del primer certificado del vehículo.'),
            ]),
        ];
    }

    private static function propietario(): array
    {
        return [
            self::grid([
                Select::make('owner_document_type')
                    ->label('Tipo de documento')
                    ->options([
                        'CC' => 'Cédula de ciudadanía',
                        'NIT' => 'NIT',
                        'CE' => 'Cédula de extranjería',
                        'TI' => 'Tarjeta de identidad',
                        'PA' => 'Pasaporte',
                    ])
                    ->default('CC')
                    ->live()
                    ->required(),

                TextInput::make('owner_document')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(20)
                    ->inputMode(fn (Get $get) => in_array($get('owner_document_type'), ['CE', 'PA']) ? 'text' : 'numeric')
                    ->helperText(fn (Get $get) => $get('owner_document_type') === 'NIT' ? 'Con o sin dígito de verificación, ej. 900123456-7' : null)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            $patron = match ($get('owner_document_type')) {
                                'CC', 'TI' => '/^\d{5,10}$/',
                                'NIT' => '/^\d{8,10}(-\d)?$/',
                                default => '/^[A-Za-z0-9]{4,20}$/',
                            };

                            if (! preg_match($patron, trim((string) $value))) {
                                $fail('El número de documento no tiene un formato válido para el tipo seleccionado.');
                            }
                        },
                    ]),

                TextInput::make('owner_nombre')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(80),

                TextInput::make('owner_apellido')
                    ->label('Apellidos')
                    // Opcional: la plataforma anterior solo pedía «Nombres»
                    ->helperText('Opcional')
                    ->maxLength(80),

                TextInput::make('owner_direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(150),

                Select::make('owner_departamento')
                    ->label('Departamento')
                    ->options(fn () => ColombiaGeo::departamentos())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('owner_ciudad', null))
                    ->required(),

                Select::make('owner_ciudad')
                    ->label('Ciudad / municipio')
                    ->options(fn (Get $get) => ColombiaGeo::ciudades($get('owner_departamento')))
                    ->searchable()
                    ->disabled(fn (Get $get) => ! $get('owner_departamento'))
                    ->required(),

                TextInput::make('owner_telefono')
                    ->label('Celular')
                    ->tel()
                    ->required()
                    ->placeholder('3001234567')
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/\D+/', '', $state) : null)
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                            if (! preg_match('/^(3\d{9}|60\d{8})$/', preg_replace('/\D+/', '', (string) $value))) {
                                $fail('Ingrese un celular de 10 dígitos (3XXXXXXXXX) o fijo con indicativo (60XXXXXXXX).');
                            }
                        },
                    ]),

                TextInput::make('owner_email')
                    ->label('Correo electrónico')
                    ->email()
                    // Opcional, como en la plataforma anterior: muchos propietarios no dejan correo
                    ->helperText('Opcional. Si lo deja, el propietario recibe el certificado y el aviso de vencimiento.')
                    ->maxLength(120),
            ]),
        ];
    }

    /** Cambio y garantía de chip no exigen reguladores ni cilindros. */
    private static function exige(mixed $tipoId, string $requisito): bool
    {
        return ServiceType::find($tipoId)?->exige($requisito) ?? true;
    }

    private static function equipos(): array
    {
        return [
            Repeater::make('regulators')
                ->label('Reguladores')
                ->relationship()
                ->schema([
                    self::grid([
                        Select::make('brand_id')
                            ->label('Marca')
                            ->options(fn () => RegulatorBrand::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('numero_serie')
                            ->label('Número de serie')
                            ->required()
                            ->maxLength(60)
                            ->distinct()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null),
                    ]),
                ])
                ->itemLabel(fn (array $state) => filled($state['numero_serie'] ?? null) ? 'Regulador '.$state['numero_serie'] : 'Nuevo regulador')
                ->addActionLabel('Agregar regulador')
                ->defaultItems(1)
                ->minItems(fn (Get $get) => self::exige($get('service_type_id'), 'reguladores') ? 1 : 0)
                ->collapsible()
                ->columnSpanFull(),

            Repeater::make('cilindros')
                ->label('Cilindros')
                ->relationship()
                ->schema([
                    self::grid([
                        Select::make('brand_id')
                            ->label('Marca')
                            ->options(fn () => CylinderBrand::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('numero_serie')
                            ->label('Número de serie')
                            ->required()
                            ->maxLength(60)
                            ->distinct()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null),

                        TextInput::make('capacidad')
                            ->label('Capacidad')
                            ->suffix('litros')
                            ->numeric()
                            ->inputMode('numeric')
                            ->minValue(1)
                            ->maxValue(500)
                            ->required(),

                        DatePicker::make('fecha_fabricacion')
                            ->label('Fecha de fabricación')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->live()
                            ->required(),

                        DatePicker::make('fecha_prueba')
                            ->label('Fecha de prueba hidrostática')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (Get $get) => $get('fecha_fabricacion'))
                            ->maxDate(now())
                            ->required(),
                    ]),
                ])
                ->itemLabel(fn (array $state) => filled($state['numero_serie'] ?? null) ? 'Cilindro '.$state['numero_serie'] : 'Nuevo cilindro')
                ->addActionLabel('Agregar cilindro')
                ->defaultItems(1)
                ->minItems(fn (Get $get) => self::exige($get('service_type_id'), 'cilindros') ? 1 : 0)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    // ------------------------------------------------------------------
    // Vehículo: búsqueda y guardado
    // ------------------------------------------------------------------

    private static function esChasis(Get $get): bool
    {
        // Se llama varias veces por render (etiqueta, placeholder, validación): una sola consulta
        static $chasisId = false;
        $chasisId = $chasisId === false ? VehicleIdentificationType::where('nombre', 'Chasis')->value('id') : $chasisId;

        return $chasisId !== null && (string) $get('vehicle_identification_type_id') === (string) $chasisId;
    }

    /** @return array<int, string> id => nombre de los servicios que puede pedir el taller de esta solicitud */
    private static function serviciosDe(Get $get, ?Solicitud $record): array
    {
        $taller = $record?->user ?? (Auth::user()?->hasRole('admin') ? User::find($get('user_id')) : Auth::user());
        $servicios = $taller ? $taller->serviciosDisponibles()->pluck('nombre', 'id')->all() : [];

        if ($record?->serviceType) {
            $servicios[$record->service_type_id] ??= $record->serviceType->nombre;
        }

        return $servicios;
    }

    /**
     * Al digitar la placa: consulta SICOM y precarga lo conocido del vehículo. En una solicitud nueva
     * trae además el sistema y los equipos del último servicio, como la plataforma anterior.
     */
    private static function autocompletarVehiculo(?string $identificacion, Get $get, Set $set, bool $nueva = false): void
    {
        $identificacion = strtoupper(preg_replace('/[\s-]+/', '', (string) $identificacion));

        if ($identificacion === '') {
            return;
        }

        // Como en la plataforma anterior: primero SICOM y después los datos propios
        $sicom = self::consultarSicom($identificacion, self::esChasis($get));
        $vehiculo = Vehicle::where(self::esChasis($get) ? 'vin' : 'placa', $identificacion)->first();

        if ($sicom && blank($get('fecha_instalacion')) && filled($sicom['fecha_instalacion'] ?? null)) {
            $set('fecha_instalacion', substr($sicom['fecha_instalacion'], 0, 10));
        }

        foreach ($vehiculo ? self::CAMPOS_VEHICULO : [] as $campo) {
            $set($campo, $vehiculo->{$campo});
        }

        // El propietario no se precarga (puede haber cambiado) y el cambio de chip no cuenta como servicio previo
        $previo = $nueva ? Solicitud::query()
            ->where('vehicle_identification', $identificacion)
            ->whereDoesntHave('serviceType', fn ($tipo) => $tipo->where('clase', 'cambio_chip'))
            ->whereNotIn('estado', [EstadoSolicitud::Rechazada->value, EstadoSolicitud::Cancelada->value])
            ->with(['regulators', 'cilindros'])
            ->latest('id')
            ->first() : null;

        if ($previo) {
            foreach (['combustion_system_id', 'application_type_id', 'technology_id'] as $campo) {
                $set($campo, $previo->{$campo});
            }

            $set('regulators', $previo->regulators->map->only(['brand_id', 'numero_serie'])->all());
            $set('cilindros', $previo->cilindros->map(fn ($cilindro) => [
                ...$cilindro->only(['brand_id', 'numero_serie', 'capacidad']),
                'fecha_fabricacion' => $cilindro->getRawOriginal('fecha_fabricacion'),
                'fecha_prueba' => $cilindro->getRawOriginal('fecha_prueba'),
            ])->all());
        }

        if ($sicom) {
            Notification::make()
                ->title('Vehículo registrado en SICOM')
                ->body(collect([
                    'Estado: '.($sicom['estado'] ?? '—'),
                    'Chip: '.($sicom['idrom_chip'] ?? '—'),
                    filled($sicom['fecha_instalacion'] ?? null) ? 'Instalación: '.date('d/m/Y', strtotime($sicom['fecha_instalacion'])) : null,
                    SicomAbierto::municipio($sicom) ?: null,
                ])->filter()->implode(' · '))
                ->info()
                ->send();
        }

        if ($vehiculo) {
            Notification::make()
                ->title('Vehículo encontrado')
                ->body($previo
                    ? 'Se encontró un servicio previo para el vehículo ingresado, por lo tanto, fue pre-cargada la información del último servicio. Verifíquela antes de continuar.'
                    : 'Se completaron los datos del vehículo. Verifíquelos antes de continuar.')
                ->success()
                ->send();
        } elseif (! $sicom) {
            Notification::make()
                ->title('No se encontraron registros')
                ->body('No hay un servicio previo de este vehículo. Complete sus datos.')
                ->warning()
                ->send();
        }
    }

    /** Registro del vehículo en SICOM, null si no está y false si no se pudo consultar (no bloquea). */
    private static function consultarSicom(string $identificacion, bool $esChasis): array|false|null
    {
        if (! \App\Models\SystemSetting::enabled('sicom_consulta_evaluacion')) {
            return false;
        }

        try {
            return SicomAbierto::porVehiculo($identificacion, $esChasis);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Crea o actualiza el vehículo por placa/VIN (un vehículo puede certificarse
     * varias veces: revisión anual, quinquenal...) y devuelve los datos de la solicitud
     * sin los campos del vehículo y con vehicle_id.
     */
    public static function guardarVehiculo(array $data): array
    {
        $esChasis = VehicleIdentificationType::whereKey($data['vehicle_identification_type_id'] ?? null)->value('nombre') === 'Chasis';
        $columna = $esChasis ? 'vin' : 'placa';

        $vehiculo = Vehicle::updateOrCreate(
            [$columna => $data['vehicle_identification']],
            Arr::only($data, self::CAMPOS_VEHICULO),
        );

        return [
            ...Arr::except($data, self::CAMPOS_VEHICULO),
            'vehicle_id' => $vehiculo->getKey(),
        ];
    }

    /** Para el formulario de edición: trae los datos del vehículo a los campos planos. */
    public static function datosVehiculo(?Vehicle $vehiculo): array
    {
        return $vehiculo ? Arr::only($vehiculo->toArray(), self::CAMPOS_VEHICULO) : [];
    }
}
