<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'evaluador' => 'warning',
                        'revisor' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('city')
                    ->label('Ubicación')
                    ->formatStateUsing(fn (User $record) => trim("{$record->city}, {$record->department}", ', '))
                    ->placeholder('—')
                    ->visibleFrom('md'),

                TextColumn::make('fecha_vencimiento')
                    ->label('Cámara de comercio vence')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('asignarEvaluadores')
                        ->label('Asignar evaluadores')
                        ->icon('heroicon-o-user-plus')
                        ->visible(fn (User $record) => $record->hasRole('cliente'))
                        ->modalHeading(fn (User $record) => "Evaluadores de {$record->name}")
                        ->fillForm(fn (User $record) => ['evaluadores' => $record->evaluadores()->pluck('users.id')->all()])
                        ->schema([
                            Select::make('evaluadores')
                                ->label('Evaluadores encargados')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => User::role('evaluador')->orderBy('name')->pluck('name', 'id'))
                                ->helperText('Recibirán las solicitudes de este taller.'),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->evaluadores()->sync($data['evaluadores'] ?? []);
                            Notification::make()->title('Evaluadores actualizados')->success()->send();
                        }),

                    Action::make('asignarRevisores')
                        ->label('Asignar revisores')
                        ->icon('heroicon-o-scale')
                        ->visible(fn (User $record) => $record->hasRole('evaluador'))
                        ->modalHeading(fn (User $record) => "Revisores de {$record->name}")
                        ->fillForm(fn (User $record) => ['revisores' => $record->revisores()->pluck('users.id')->all()])
                        ->schema([
                            Select::make('revisores')
                                ->label('Revisores')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => User::role('revisor')->orderBy('name')->pluck('name', 'id'))
                                ->helperText('Auditarán las evaluaciones de este evaluador. Si no asigna ninguno, cualquier revisor podrá verlas.'),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->revisores()->sync($data['revisores'] ?? []);
                            Notification::make()->title('Revisores actualizados')->success()->send();
                        }),

                    // canDelete() del recurso impide borrarse a sí mismo o a usuarios con solicitudes
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay usuarios');
    }
}
