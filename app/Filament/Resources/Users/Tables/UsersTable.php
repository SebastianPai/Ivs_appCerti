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
                    self::inventario(),
                    ViewAction::make(),
                    EditAction::make(),

                    // Como el ícono de tienda de la plataforma anterior: desde el evaluador (o el externo)
                    // se marcan sus talleres. Es la misma asignación que «Asignar evaluadores» del taller.
                    Action::make('asignarTalleres')
                        ->label('Asignar talleres')
                        ->icon('heroicon-o-building-storefront')
                        ->visible(fn (User $record) => $record->hasAnyRole(['evaluador', 'externo']))
                        ->modalHeading(fn (User $record) => "Talleres de {$record->name}")
                        ->fillForm(fn (User $record) => ['talleres' => $record->clientesAsignados()->pluck('users.id')->all()])
                        ->schema([
                            \Filament\Forms\Components\CheckboxList::make('talleres')
                                ->label('Talleres')
                                ->options(fn () => User::role('cliente')->orderBy('name')->pluck('name', 'id'))
                                ->columns(2)
                                ->bulkToggleable()
                                ->helperText(fn (User $record) => $record->hasRole('externo')
                                    ? 'Solo podrá exportar los certificados de estos talleres.'
                                    : 'Recibirá las solicitudes de estos talleres.'),
                        ])
                        ->action(function (User $record, array $data) {
                            $cambios = $record->clientesAsignados()->sync($data['talleres'] ?? []);

                            // Igual que «Asignar evaluadores»: el taller se entera de quién lo evalúa
                            if ($record->hasRole('evaluador')) {
                                foreach (['attached' => true, 'detached' => false] as $clave => $asignado) {
                                    foreach (User::whereKey($cambios[$clave])->get() as $taller) {
                                        \App\Support\Correo::enviar($taller, 'inspector', 'Notificación Nuevo Inspector', 'emails.inspector', [
                                            'saludo' => $taller->name,
                                            'inspector' => $record->name,
                                            'asignado' => $asignado,
                                            'detalle' => 'Inspector '.($asignado ? 'asignado' : 'desvinculado').": {$record->name}",
                                        ]);
                                    }
                                }
                            }

                            Notification::make()->title('Talleres actualizados')->success()->send();
                        }),

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

    /**
     * «Inventario» de la plataforma anterior: Teléfono Móvil (dos) y Flexómetro, con los mismos
     * campos. Lo que depende de «Tiene Teléfono Móvil?» se apaga mientras diga No, como allá.
     */
    private static function inventario(): Action
    {
        $si = ['1' => 'Si', '0' => 'No'];

        $telefono = fn (string $n) => \Filament\Schemas\Components\Fieldset::make("Teléfono {$n}")
            ->columns(['default' => 1, 'md' => 3])
            ->schema([
                Select::make("tiene_movil{$n}")->label('Tiene Teléfono Móvil?')->options($si)->default('0')->selectablePlaceholder(false)->live(),
                \Filament\Forms\Components\TextInput::make("movil{$n}_marca")->label('Marca Teléfono Móvil')->maxLength(100)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get("tiene_movil{$n}")),
                \Filament\Forms\Components\TextInput::make("movil{$n}_modelo")->label('Modelo Teléfono Móvil')->maxLength(100)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get("tiene_movil{$n}")),
                \Filament\Forms\Components\TextInput::make("movil{$n}_serial")->label('Serial Teléfono Móvil')->maxLength(100)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get("tiene_movil{$n}")),
                Select::make("movil{$n}_tiene_cargador")->label('Teléfono Móvil Tiene Cargador?')->options($si)->default('0')->selectablePlaceholder(false)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get("tiene_movil{$n}")),
                Select::make("movil{$n}_tiene_caja")->label('Teléfono Móvil Tiene Caja?')->options($si)->default('0')->selectablePlaceholder(false)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get("tiene_movil{$n}")),
            ]);

        return Action::make('inventario')
            ->label('Inventario')
            ->icon('heroicon-o-archive-box')
            ->modalHeading(fn (User $record) => "Inventario {$record->name}")
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(function (User $record) {
                $datos = $record->inventario?->attributesToArray() ?? [];

                // Los Si/No viajan como texto para el select
                return collect($datos)->map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : $v)->all();
            })
            ->schema([
                \Filament\Schemas\Components\Tabs::make()->tabs([
                    \Filament\Schemas\Components\Tabs\Tab::make('Teléfono Móvil')->schema([$telefono('1'), $telefono('2')]),
                    \Filament\Schemas\Components\Tabs\Tab::make('Flexómetro')->columns(['default' => 1, 'md' => 3])->schema([
                        \Filament\Forms\Components\TextInput::make('flexometro_serial')->label('Número Serial')->placeholder('Ingresar número serial')->maxLength(100),
                        \Filament\Forms\Components\DatePicker::make('flexometro_fecha_entrega')->label('Fecha Entrega')->native(false)->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\DatePicker::make('flexometro_fecha_verificacion')->label('Fecha Verificación')->native(false)->displayFormat('d/m/Y'),
                    ]),
                ]),
            ])
            ->action(function (User $record, array $data) {
                foreach (['1', '2'] as $n) {
                    // Sin teléfono no se guardan datos viejos del equipo
                    if (! ($data["tiene_movil{$n}"] ?? false)) {
                        foreach (['marca', 'modelo', 'serial'] as $campo) {
                            $data["movil{$n}_{$campo}"] = null;
                        }
                        $data["movil{$n}_tiene_cargador"] = $data["movil{$n}_tiene_caja"] = false;
                    }
                }

                // Los Si/No que quedaron apagados llegan vacíos: son No
                foreach (['tiene_movil1', 'movil1_tiene_cargador', 'movil1_tiene_caja', 'tiene_movil2', 'movil2_tiene_cargador', 'movil2_tiene_caja'] as $campo) {
                    $data[$campo] = (bool) ($data[$campo] ?? false);
                }

                $record->inventario()->updateOrCreate([], collect($data)
                    ->only(\App\Models\InventarioUsuario::CAMPOS)
                    ->all());

                \App\Models\Actividad::registrar('usuario', "Inventario de {$record->name} actualizado", $record);
                Notification::make()->title('Inventario guardado')->success()->send();
            });
    }
}
