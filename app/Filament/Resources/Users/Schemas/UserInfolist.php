<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuenta')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Correo')->copyable(),
                        TextEntry::make('roles.name')->label('Rol')->badge(),
                        TextEntry::make('department')->label('Departamento')->placeholder('—'),
                        TextEntry::make('city')->label('Ciudad')->placeholder('—'),
                        TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y'),
                    ]),
                ]),

            Section::make('Asignaciones')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('evaluadores.name')->label('Evaluadores asignados')->badge()->placeholder('Ninguno')
                        ->visible(fn (User $record) => $record->hasRole('cliente')),
                    TextEntry::make('clientesAsignados.name')->label('Talleres que evalúa')->badge()->placeholder('Ninguno')
                        ->visible(fn (User $record) => $record->hasRole('evaluador')),
                    TextEntry::make('revisores.name')->label('Revisores que lo auditan')->badge()->placeholder('Cualquier revisor')
                        ->visible(fn (User $record) => $record->hasRole('evaluador')),
                    TextEntry::make('evaluadoresSupervisados.name')->label('Evaluadores que supervisa')->badge()->placeholder('Los que no tienen revisor asignado')
                        ->visible(fn (User $record) => $record->hasRole('revisor')),
                ]),

            Section::make('Taller')
                ->columnSpanFull()
                ->visible(fn (User $record) => (bool) $record->is_taller)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextEntry::make('camara_comercio')
                            ->label('Cámara de comercio')
                            ->formatStateUsing(fn ($state) => $state ? 'Ver documento' : null)
                            ->placeholder('No cargada')
                            ->url(fn (User $record) => $record->camara_comercio ? route('usuarios.camara', $record) : null, shouldOpenInNewTab: true)
                            ->color('primary'),
                        TextEntry::make('fecha_radicado')->label('Radicado')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->placeholder('—')
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success'),
                    ]),
                ]),
        ]);
    }
}
