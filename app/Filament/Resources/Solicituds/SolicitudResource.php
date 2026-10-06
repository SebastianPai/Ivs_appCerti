<?php

namespace App\Filament\Resources\Solicituds;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Filament\Resources\Solicituds\Pages\EditSolicitud;
use App\Filament\Resources\Solicituds\Pages\ListSolicituds;
use App\Filament\Resources\Solicituds\Pages\ManageSolicitudAttachments;
use App\Filament\Resources\Solicituds\Pages\ViewSolicitud;
use App\Filament\Resources\Solicituds\Schemas\SolicitudForm;
use App\Filament\Resources\Solicituds\Tables\SolicitudsTable;
use App\Models\Solicitud;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SolicitudResource extends Resource
{
    protected static ?string $model = Solicitud::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Taller';

    protected static ?string $navigationLabel = 'Mis solicitudes';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SolicitudForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SolicitudsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolicituds::route('/'),
            'create' => CreateSolicitud::route('/create'),
            'view' => ViewSolicitud::route('/{record}'),
            'edit' => EditSolicitud::route('/{record}/edit'),
            'attachments' => ManageSolicitudAttachments::route('/{record}/attachments'),
        ];
    }

    // -------- Permisos (sin esto Filament deja entrar a cualquier usuario logueado) --------

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'cliente']);
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasRole('cliente');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    /** El taller solo edita mientras nadie la ha evaluado o si se la devolvieron. */
    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        return (bool) ($user?->hasRole('admin') || ($user?->hasRole('cliente') && $record->esEditablePorTaller()));
    }

    /** Nunca se borran solicitudes aprobadas (son el respaldo del certificado). */
    public static function canDelete(Model $record): bool
    {
        return (bool) Auth::user()?->hasRole('admin') && ! $record->estaAprobada();
    }

    public static function canDeleteAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        // El taller solo ve lo suyo
        return $query->where('user_id', $user?->id);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Auth::user()?->hasRole('cliente')) {
            return null;
        }

        $devueltas = static::getEloquentQuery()->where('estado', EstadoSolicitud::DevueltaTaller->value)->count();

        return $devueltas ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitudes devueltas que debe corregir';
    }
}
