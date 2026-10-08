<?php

namespace App\Filament\Resources\Actividades;

use App\Filament\Resources\Actividades\Pages\ListActividades;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\Actividad;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Registro de auditoría: solo lectura y solo para el administrador. */
class ActividadResource extends Resource
{
    protected static ?string $model = Actividad::class;

    protected static ?string $slug = 'auditoria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 98;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i:s a'),
                TextEntry::make('user.name')->label('Usuario')->placeholder('Sistema'),
                TextEntry::make('evento')->label('Evento')->badge()
                    ->formatStateUsing(fn (Actividad $record) => $record->eventoLabel())
                    ->color(fn (Actividad $record) => $record->eventoColor()),
                TextEntry::make('solicitud.vehicle_identification')->label('Solicitud')->placeholder('—'),
                TextEntry::make('ip')->label('IP')->placeholder('—'),
                TextEntry::make('user_agent')->label('Navegador')->placeholder('—'),
            ]),
            TextEntry::make('descripcion')->label('Descripción'),
            KeyValueEntry::make('cambios')
                ->label('Detalle de los cambios')
                ->keyLabel('Campo')
                ->valueLabel('Valor')
                ->state(fn (Actividad $record) => collect($record->cambios ?? [])
                    ->map(fn ($v) => is_array($v) && array_key_exists('antes', $v)
                        ? self::texto($v['antes']).'  →  '.self::texto($v['despues'])
                        : self::texto($v))
                    ->all())
                ->visible(fn (Actividad $record) => filled($record->cambios)),
        ]);
    }

    private static function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => '(vacío)',
            is_bool($valor) => $valor ? 'sí' : 'no',
            is_array($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE),
            default => (string) $valor,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,name', 'solicitud:id,vehicle_identification']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->sortable(),
                TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema')->searchable(),
                TextColumn::make('evento')->label('Evento')->badge()
                    ->formatStateUsing(fn (Actividad $record) => $record->eventoLabel())
                    ->color(fn (Actividad $record) => $record->eventoColor()),
                TextColumn::make('descripcion')->label('Descripción')->wrap()->limit(120)->searchable(),
                TextColumn::make('solicitud.vehicle_identification')
                    ->label('Placa')
                    ->placeholder('—')
                    ->url(fn (Actividad $record) => $record->solicitud ? SolicitudResource::getUrl('view', ['record' => $record->solicitud_id]) : null)
                    ->searchable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('evento')->label('Evento')->options(Actividad::EVENTOS)->multiple(),
                SelectFilter::make('user_id')->label('Usuario')->relationship('user', 'name')->searchable()->preload(),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Aquí aparecerá cada acción realizada en la plataforma.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActividades::route('/'),
        ];
    }
}
