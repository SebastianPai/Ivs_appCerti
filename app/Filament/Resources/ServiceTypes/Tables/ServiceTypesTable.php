<?php

namespace App\Filament\Resources\ServiceTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable(),

                TextColumn::make('descripcion')
                    ->label('Tipo')
                    ->badge(),

                IconColumn::make('activo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('toggle_active')
                    ->label(fn ($record) =>
                        $record->activo ? 'Desactivar' : 'Activar'
                    )
                    ->icon(fn ($record) =>
                        $record->activo
                            ? 'heroicon-o-x-circle'
                            : 'heroicon-o-check-circle'
                    )
                    ->color(fn ($record) =>
                        $record->activo ? 'danger' : 'success'
                    )
                    ->requiresConfirmation()
                    ->action(fn ($record) =>
                        $record->update([
                            'activo' => ! $record->activo
                        ])
                    ),
            ])
            ->toolbarActions([
                // ❌ Quitamos Delete masivo
            ]);
    }
}
