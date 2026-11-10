<?php

namespace App\Filament\Resources\ServiceTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class ServiceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required(),
                Select::make('descripcion')
                    ->label('Tipo de servicio')
                    ->options([
                        'particular' => 'Particular',
                        'convenio'   => 'Convenio',
                    ])
                    ->nullable()
                    ->native(false),
                Select::make('clase')
                    ->label('Clase de servicio')
                    ->options(\App\Models\ServiceType::CLASES)
                    ->placeholder('Otro')
                    ->native(false)
                    ->helperText('Inicial: la instalación es la fecha del servicio. Cambio de chip: no sale en el exportable SICOM por certificado.'),
                TextInput::make('codigo_exportado')
                    ->label('Código en exportables SICOM')
                    ->maxLength(10)
                    ->helperText('Columna id_tipo_certificado. Vacío = el número interno del tipo.'),
                TextInput::make('nombre_exportado')
                    ->label('Nombre en exportables SICOM')
                    ->maxLength(120)
                    ->helperText('Columna tipo_certificacion, p. ej. REVISION INICIAL. Vacío = el nombre del tipo.'),
                Toggle::make('chip_nuevo')
                    ->label('Exige chip nuevo')
                    ->helperText('El evaluador no puede usar un chip que ya esté en otra solicitud.'),
                Toggle::make('fotos_obligatorias')
                    ->label('Fotos del evaluador obligatorias')
                    ->default(true),
                \Filament\Forms\Components\CheckboxList::make('requisitos')
                    ->label('Qué debe traer el taller')
                    ->options(\App\Models\ServiceType::REQUISITOS)
                    ->default(array_keys(\App\Models\ServiceType::REQUISITOS))
                    ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? array_keys(\App\Models\ServiceType::REQUISITOS)))
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->required(),
            ]);
    }
}
