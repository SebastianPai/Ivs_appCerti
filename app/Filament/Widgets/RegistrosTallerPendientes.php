<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio del admin: talleres que no pueden entrar porque su registro de Industria y Comercio
 * está vencido o sin fecha. Primero los que ya cargaron el documento y esperan la fecha.
 */
class RegistrosTallerPendientes extends TableWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasRole('admin') && User::conRegistroPendiente()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Talleres pendientes de fecha de registro')
            ->description('No pueden entrar a la aplicación hasta que se les asigne la fecha del registro de Industria y Comercio.')
            ->query(fn () => User::conRegistroPendiente()
                ->orderByRaw('camara_comercio IS NULL')
                ->orderBy('name'))
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('name')->label('Taller')->searchable(),
                TextColumn::make('nit')->label('NIT')->placeholder('—'),
                TextColumn::make('city')->label('Ciudad')->placeholder('—'),
                TextColumn::make('camara_comercio')
                    ->label('Documento')
                    ->badge()
                    ->state(fn (User $record) => match (true) {
                        blank($record->camara_comercio) => 'Sin documento',
                        $record->fecha_vencimiento === null => 'Cargado, falta la fecha',
                        default => 'Vencido',
                    })
                    ->color(fn (string $state) => $state === 'Cargado, falta la fecha' ? 'warning' : 'danger'),
                TextColumn::make('fecha_vencimiento')->label('Venció')->date('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('documento')
                    ->label('Click para descargar documento')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (User $record) => route('usuarios.camara', $record), shouldOpenInNewTab: true)
                    ->visible(fn (User $record) => filled($record->camara_comercio)),
                Action::make('asignar')
                    ->label('Asignar')
                    ->icon('heroicon-o-calendar-days')
                    ->modalHeading('Registro Industria y Comercio')
                    ->modalSubmitActionLabel('Asignar')
                    ->schema([
                        DatePicker::make('fecha_radicado')
                            ->label('Fecha expedición registro')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->default(now()->toDateString())
                            ->maxDate(now())
                            ->minDate(now()->subYear()->addDay())
                            ->live()
                            // Como en la plataforma anterior: se ve el vencimiento antes de asignar
                            ->helperText(fn (?string $state) => $state
                                ? 'Fecha vencimiento registro: '.Carbon::parse($state)->addYear()->format('d/m/Y')
                                : 'El registro vence un año después de la expedición.')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $vence = Carbon::parse($data['fecha_radicado'])->addYear();
                        $record->update(['fecha_radicado' => $data['fecha_radicado'], 'fecha_vencimiento' => $vence->toDateString()]);

                        Notification::make()->title('Fecha asignada')->body("El taller ya puede entrar. Su registro vence el {$vence->format('d/m/Y')}.")->success()->send();
                    }),
            ])
            ->emptyStateHeading('Ningún taller pendiente');
    }
}
