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
                    ->formatStateUsing(fn (string $state) => User::ROLES[$state] ?? ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'evaluador' => 'warning',
                        'revisor' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('activo')
                    ->label('Acceso')
                    ->badge()
                    ->state(fn (User $record) => match (true) {
                        ! $record->activo => 'Sin Acceso',
                        ! $record->hasVerifiedEmail() => 'Correo sin confirmar',
                        $record->debeActualizarRegistro() => 'Registro pendiente',
                        default => 'Con acceso',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Con acceso' => 'success',
                        'Sin Acceso' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('city')
                    ->label('Ubicación')
                    ->formatStateUsing(fn (User $record) => trim("{$record->city}, {$record->department}", ', '))
                    ->placeholder('—')
                    ->visibleFrom('md'),

                TextColumn::make('fecha_vencimiento')
                    ->label('Registro vence')
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
                            $cambios = $record->evaluadores()->sync($data['evaluadores'] ?? []);

                            // «Notificación Nuevo Inspector»: el taller se entera de quién lo evalúa y de quién deja de hacerlo
                            foreach (['attached' => true, 'detached' => false] as $clave => $asignado) {
                                foreach (User::whereKey($cambios[$clave])->pluck('name') as $inspector) {
                                    \App\Support\Correo::enviar($record, 'inspector', 'Notificación Nuevo Inspector', 'emails.inspector', [
                                        'saludo' => $record->name,
                                        'inspector' => $inspector,
                                        'asignado' => $asignado,
                                        'detalle' => 'Inspector '.($asignado ? 'asignado' : 'desvinculado').": {$inspector}",
                                    ]);
                                }
                            }

                            Notification::make()->title('Evaluadores actualizados')->success()->send();
                        }),

                    Action::make('cambiarAcceso')
                        ->label(fn (User $record) => $record->activo ? 'Quitar acceso' : 'Devolver acceso')
                        ->icon(fn (User $record) => $record->activo ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                        ->color(fn (User $record) => $record->activo ? 'danger' : 'success')
                        ->visible(fn (User $record) => ! $record->is(\Illuminate\Support\Facades\Auth::user()))
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record) => $record->activo ? "¿Quitar el acceso a {$record->name}?" : "¿Devolver el acceso a {$record->name}?")
                        ->modalDescription(fn (User $record) => $record->activo
                            ? 'No podrá entrar y se le cerrará la sesión. Su rol, sus solicitudes y sus asignaciones se conservan.'
                            : 'Podrá entrar de nuevo con su rol de siempre.')
                        ->action(function (User $record) {
                            $record->update(['activo' => ! $record->activo]);
                            Notification::make()->title($record->activo ? 'Acceso devuelto' : 'Cuenta sin acceso')->success()->send();
                        }),

                    Action::make('reenviarConfirmacion')
                        ->label('Reenviar confirmación de correo')
                        ->icon('heroicon-o-envelope')
                        ->visible(fn (User $record) => ! $record->hasVerifiedEmail())
                        ->action(function (User $record) {
                            $record->enviarConfirmacionDeCorreo();
                            Notification::make()->title('Correo de confirmación enviado')->body($record->email)->success()->send();
                        }),

                    Action::make('confirmarCorreo')
                        ->label('Dar el correo por confirmado')
                        ->icon('heroicon-o-check-badge')
                        ->visible(fn (User $record) => ! $record->hasVerifiedEmail())
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record) => "¿Dar por confirmado el correo de {$record->name}?")
                        ->modalDescription('Úselo solo si ya comprobó por otro medio que el correo es de esa persona.')
                        ->action(function (User $record) {
                            $record->markEmailAsVerified();
                            Notification::make()->title('Correo confirmado')->success()->send();
                        }),

                    Action::make('asignarServicios')
                        ->label('Asignación de servicios')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->visible(fn (User $record) => $record->hasRole('cliente'))
                        ->modalHeading(fn (User $record) => "Servicios de {$record->name}")
                        ->fillForm(fn (User $record) => ['servicios' => $record->servicios()->pluck('service_types.id')->all()])
                        ->schema([
                            \Filament\Forms\Components\CheckboxList::make('servicios')
                                ->label('Servicios disponibles')
                                ->options(fn () => \App\Models\ServiceType::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                                ->columns(2)
                                ->bulkToggleable()
                                ->helperText('El taller solo podrá crear solicitudes de los servicios marcados. Sin ninguno, no puede crear solicitudes.'),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->servicios()->sync($data['servicios'] ?? []);
                            \App\Models\Actividad::registrar('actualizado', "Servicios de {$record->name}: ".(\App\Models\ServiceType::whereKey($data['servicios'] ?? [])->orderBy('nombre')->pluck('nombre')->implode(', ') ?: 'ninguno'), $record);
                            Notification::make()->title('Servicios actualizados')->success()->send();
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
