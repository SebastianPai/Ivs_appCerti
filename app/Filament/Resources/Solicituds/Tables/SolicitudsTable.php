<?php

namespace App\Filament\Resources\Solicituds\Tables;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\Solicitud;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SolicitudsTable
{
    public static function configure(Table $table): Table
    {
        $esAdmin = fn () => (bool) Auth::user()?->hasRole('admin');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle.brand', 'vehicle.model', 'serviceType', 'user']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('vehicle_identification')
                    ->label('Placa / chasis')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Solicitud $record) => trim(($record->vehicle?->brand?->nombre ?? '').' '.($record->vehicle?->model?->nombre ?? '').' '.($record->vehicle?->year ?? ''))),

                TextColumn::make('user.name')
                    ->label('Taller')
                    ->searchable()
                    ->visible($esAdmin),

                TextColumn::make('serviceType.nombre')
                    ->label('Servicio')
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EstadoSolicitud::labelDe($state))
                    ->color(fn (?string $state) => EstadoSolicitud::colorDe($state))
                    ->sortable(),

                TextColumn::make('observacion_devolucion')
                    ->label('Qué corregir')
                    ->color('danger')
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'requiere_accion'),

                TextColumn::make('codigo')
                    ->label('Certificado')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->since()
                    ->sortable()
                    ->visibleFrom('lg'),
            ])

            ->recordActions([
                // Acción principal según el estado (lo que el taller tiene que hacer ahora)
                Action::make('corregir')
                    ->label('Corregir')
                    ->icon('heroicon-o-pencil-square')
                    ->color('danger')
                    ->button()
                    ->url(fn (Solicitud $record) => SolicitudResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Solicitud $record) => $record->estado === EstadoSolicitud::DevueltaTaller->value && SolicitudResource::canEdit($record)),

                Action::make('certificado')
                    ->label('Certificado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->button()
                    ->url(fn (Solicitud $record) => route('solicitud.certificado', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Solicitud $record) => $record->estaAprobada()),

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('documentos')
                        ->label('Documentos')
                        ->icon('heroicon-o-paper-clip')
                        ->url(fn (Solicitud $record) => SolicitudResource::getUrl('attachments', ['record' => $record]))
                        ->visible(fn (Solicitud $record) => SolicitudResource::canEdit($record)),
                ]),
            ])
            ->recordUrl(fn (Solicitud $record) => SolicitudResource::getUrl('view', ['record' => $record]))

            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoSolicitud::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])->all()),

                SelectFilter::make('user_id')
                    ->label('Taller')
                    ->relationship('user', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible($esAdmin),

                Filter::make('created_at')
                    ->label('Rango de fechas')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    // Las aprobadas no se borran (ver Solicitud::booted)
                    DeleteBulkAction::make()->visible($esAdmin),
                ]),
            ])
            ->emptyStateHeading('No hay solicitudes aquí')
            ->emptyStateDescription('Cree una nueva solicitud para iniciar la certificación de un vehículo.')
            ->emptyStateIcon('heroicon-o-document-plus');
    }
}
