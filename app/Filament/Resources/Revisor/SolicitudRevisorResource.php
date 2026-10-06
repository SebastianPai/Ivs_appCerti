<?php

namespace App\Filament\Resources\Revisor;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Revisor\Pages\ListSolicitudRevisors;
use App\Filament\Resources\Revisor\Pages\ViewSolicitudRevisor;
use App\Filament\Resources\Revisor\Schemas\SolicitudRevisorInfolist;
use App\Filament\Resources\Revisor\Tables\SolicitudRevisorTable;
use App\Models\Solicitud;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SolicitudRevisorResource extends Resource
{
    protected static ?string $model = Solicitud::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Auditoría final';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes';

    protected static string|\UnitEnum|null $navigationGroup = 'Revisión';

    protected static ?string $slug = 'revisor/auditoria';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return SolicitudRevisorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SolicitudRevisorTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolicitudRevisors::route('/'),
            'view' => ViewSolicitudRevisor::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'revisor']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        $query = parent::getEloquentQuery()->whereIn('estado', [
            EstadoSolicitud::Evaluada->value,
            EstadoSolicitud::CorreccionTecnica->value,
            EstadoSolicitud::Aprobada->value,
        ]);

        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->hasRole('revisor')) {
            return $query->visiblesParaRevisor($user);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Auth::user()?->hasRole('revisor')) {
            return null;
        }

        return static::getEloquentQuery()->where('estado', EstadoSolicitud::Evaluada->value)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
