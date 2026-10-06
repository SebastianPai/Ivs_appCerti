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
    public const CAMPOS_VEHICULO = ['brand_id', 'model_id', 'vehicle_type_id', 'year', 'fuel_base', 'motor'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehículo')->icon('heroicon-o-truck')->schema(self::vehiculo())->columnSpanFull(),
            Section::make('Servicio')->icon('heroicon-o-wrench')->schema(self::servicio())->columnSpanFull(),
            Section::make('Propietario')->icon('heroicon-o-user')->schema(self::propietario())->columnSpanFull(),
            Section::make('Equipos instalados')->icon('heroicon-o-beaker')->schema(self::equipos())->columnSpanFull(),
        ]);
    }

    /** @return array<Step> */
    public static function steps(): array
    {
        return [
            Step::make('Vehículo')->icon('heroicon-o-truck')->description('Placa y datos')->schema(self::vehiculo()),
            Step::make('Servicio')->icon('heroicon-o-wrench')->description('Tipo y sistema')->schema(self::servicio()),
            Step::make('Propietario')->icon('heroicon-o-user')->description('Datos de contacto')->schema(self::propietario()),
            Step::make('Equipos')->icon('heroicon-o-beaker')->description('Reguladores y cilindros')->schema(self::equipos()),
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
                    ->helperText('Si el vehículo ya fue certificado antes, sus datos se completan solos.')
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
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::autocompletarVehiculo($state, $get, $set)),

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

                TextInput::make('motor')
                    ->label('Número de motor')
                    ->maxLength(40)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null),
            ]),
        ];
    }

    private static function servicio(): array
    {
        return [
            self::grid([
                Select::make('service_type_id')
                    ->label('Tipo de servicio')
                    ->options(fn () => ServiceType::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                    ->required(),

                Select::make('combustion_system_id')
                    ->label('Sistema de combustión')
                    ->options(fn () => CombustionSystem::orderBy('nombre')->pluck('nombre', 'id'))
                    ->required(),

                Select::make('application_type_id')
                    ->label('Tipo de aplicación')
                    ->options(fn () => ApplicationType::orderBy('nombre')->pluck('nombre', 'id'))
                    ->required(),

                Select::make('technology_id')
                    ->label('Tecnología')
                    ->options(fn () => Technology::orderBy('nombre')->pluck('nombre', 'id'))
                    ->required(),
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
                    ->required(fn (Get $get) => $get('owner_document_type') !== 'NIT')
                    ->helperText(fn (Get $get) => $get('owner_document_type') === 'NIT' ? 'Opcional para empresas' : null)
                    ->maxLength(80),

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
                    ->required()
                    ->maxLength(120),

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

                TextInput::make('owner_direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(150)
                    ->columnSpanFull(),
            ]),
        ];
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
                ->minItems(1)
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
                ->minItems(1)
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

    private static function autocompletarVehiculo(?string $identificacion, Get $get, Set $set): void
    {
        $identificacion = strtoupper(preg_replace('/[\s-]+/', '', (string) $identificacion));

        if ($identificacion === '') {
            return;
        }

        $vehiculo = Vehicle::where(self::esChasis($get) ? 'vin' : 'placa', $identificacion)->first();

        if (! $vehiculo) {
            return;
        }

        foreach (self::CAMPOS_VEHICULO as $campo) {
            $set($campo, $vehiculo->{$campo});
        }

        Notification::make()
            ->title('Vehículo encontrado')
            ->body('Se completaron los datos del vehículo. Verifíquelos antes de continuar.')
            ->success()
            ->send();
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
