<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\ColombiaGeo;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuenta')
                ->icon('heroicon-o-user')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('name')
                            ->label('Nombre / razón social')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(120),

                        Select::make('role')
                            ->label('Rol')
                            ->options(fn () => Role::orderBy('name')->pluck('name', 'name')->map(fn ($r) => User::ROLES[$r] ?? ucfirst($r)))
                            ->required()
                            ->live()
                            ->helperText('Cliente = taller que solicita · Inspector = evaluador que inspecciona · Revisor = audita · Externo Exportado = solo descarga «Mis Exportados» (el archivo SICOM por certificado), sin ver solicitudes'),

                        Toggle::make('activo')
                            ->label('Cuenta con acceso')
                            ->default(true)
                            ->disabled(fn (?User $record) => $record?->is(Auth::user()) ?? false)
                            ->helperText('Apagado equivale a «Sin Acceso»: la persona no puede entrar, pero conserva su rol, su historial y su lugar en los listados.'),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->rule('min:8')
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Déjela vacía para no cambiarla.' : 'Mínimo 8 caracteres.')
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create'),
                    ]),
                ]),

            Section::make('Ubicación')
                ->icon('heroicon-o-map-pin')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('department')
                            ->label('Departamento')
                            ->options(fn () => ColombiaGeo::departamentos())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('city', null))
                            ->required(),

                        Select::make('city')
                            ->label('Ciudad')
                            ->options(fn (Get $get) => ColombiaGeo::ciudades($get('department')))
                            ->searchable()
                            ->disabled(fn (Get $get) => ! $get('department'))
                            ->required(),

                        // Como en la plataforma anterior, que la guardaba con el usuario
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                ]),

            Section::make('Datos del taller')
                ->icon('heroicon-o-building-storefront')
                ->columnSpanFull()
                ->visible(fn (Get $get) => $get('role') === 'cliente')
                ->schema([
                    TextInput::make('nit')
                        ->label('NIT')
                        ->placeholder('900123456-7')
                        ->maxLength(20)
                        ->regex('/^\d{5,15}(-\d)?$/')
                        ->validationMessages(['regex' => 'Escriba el NIT solo con números y, si lo tiene, el dígito de verificación tras un guion.'])
                        ->helperText('Sale en el exportable SICOM por certificado.'),
                    // Todo cliente es taller: el registro de Industria y Comercio aplica siempre
                    Grid::make(['default' => 1, 'md' => 3])
                        ->schema([
                            FileUpload::make('camara_comercio')
                                ->label('Registro Industria y Comercio')
                                ->disk('local')
                                ->visibility('private')
                                ->directory('camara-comercio')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->openable()
                                ->downloadable(),

                            DatePicker::make('fecha_radicado')
                                ->label('Fecha expedición registro')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set(
                                    'fecha_vencimiento',
                                    $state ? Carbon::parse($state)->addYear()->toDateString() : null
                                )),

                            DatePicker::make('fecha_vencimiento')
                                ->label('Fecha vencimiento registro')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->helperText('Se calcula a un año de la expedición. Vencido o sin fecha, el taller no puede entrar.')
                                ->disabled()
                                ->dehydrated(),
                        ]),
                ]),
        ]);
    }
}
