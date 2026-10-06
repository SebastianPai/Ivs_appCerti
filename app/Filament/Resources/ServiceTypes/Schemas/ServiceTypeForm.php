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
                Toggle::make('activo')
                    ->required(),
            ]);
    }
}
