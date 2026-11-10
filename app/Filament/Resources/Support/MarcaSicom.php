<?php

namespace App\Filament\Resources\Support;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Formulario y tabla compartidos por los catálogos de marcas de cilindros y reguladores:
 * nombre + código SICOM (el que viaja en los exportables SICOM en lugar del nombre).
 */
class MarcaSicom
{
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelo */
    public static function form(Schema $schema, string $modelo): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(120)
                ->unique($modelo, 'nombre', ignoreRecord: true),
            TextInput::make('codigo_sicom')
                ->label('Código SICOM')
                ->maxLength(20)
                ->helperText('Identificador de la marca en SICOM. Sin él, la marca sale vacía en los exportables SICOM.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Como «Gestión marca de regulador / cilindro» en la plataforma anterior: Id | Id Sicom | Descripción
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('Id')->sortable(),
                TextColumn::make('codigo_sicom')
                    ->label('Id Sicom')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sin código'),
                TextColumn::make('nombre')->label('Descripción')->searchable()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
