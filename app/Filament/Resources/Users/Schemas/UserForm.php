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
                            ->options(fn () => Role::orderBy('name')->pluck('name', 'name')->map(fn ($r) => ucfirst($r)))
                            ->required()
                            ->live()
                            ->helperText('cliente = taller que solicita · evaluador = inspecciona · revisor = aprueba'),

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
                    ]),
                ]),

            Section::make('Datos del taller')
                ->icon('heroicon-o-building-storefront')
                ->columnSpanFull()
                ->visible(fn (Get $get) => $get('role') === 'cliente')
                ->schema([
                    Toggle::make('is_taller')
                        ->label('Es un taller certificado')
                        ->live(),

                    Grid::make(['default' => 1, 'md' => 3])
                        ->visible(fn (Get $get) => (bool) $get('is_taller'))
                        ->schema([
                            FileUpload::make('camara_comercio')
                                ->label('Cámara de comercio')
                                ->disk('public')
                                ->directory('camara-comercio')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->openable()
                                ->downloadable(),

                            DatePicker::make('fecha_radicado')
                                ->label('Fecha de radicado')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set(
                                    'fecha_vencimiento',
                                    $state ? Carbon::parse($state)->addYear()->toDateString() : null
                                )),

                            DatePicker::make('fecha_vencimiento')
                                ->label('Vence')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->helperText('Se calcula a un año del radicado.')
                                ->disabled()
                                ->dehydrated(),
                        ]),
                ]),
        ]);
    }
}
