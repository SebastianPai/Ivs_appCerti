<?php

namespace App\Filament\Resources\Evaluador\Solicituds;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\Pages;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Flujo del evaluador (3 pasos, cada uno exige el anterior):
 *   1. verificacion → declaración de no conflicto de interés + GPS
 *   2. view         → lectura del chip, revisión de documentos y fotos de campo
 *   3. checklist    → inspección técnica NTC y envío al revisor
 */
class SolicitudResource extends Resource
{
    protected static ?string $model = Solicitud::class;

    protected static ?string $slug = 'evaluacion/solicitudes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Solicitudes por evaluar';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes';

    protected static string|\UnitEnum|null $navigationGroup = 'Evaluación';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return Tables\SolicitudesAbiertasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudesAbiertas::route('/'),
            'verificacion' => Pages\VerificacionPrevia::route('/{record}/verificacion'),
            'view' => Pages\ViewSolicitud::route('/{record}'),
            'checklist' => Pages\EvaluacionChecklist::route('/{record}/checklist'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'evaluador']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->hasRole('evaluador')) {
            // Solo solicitudes de los talleres asignados a este evaluador
            return $query->whereIn('user_id', $user->clientesAsignados()->select('users.id'));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Auth::user()?->hasRole('evaluador')) {
            return null;
        }

        return static::getEloquentQuery()->whereIn('estado', EstadoSolicitud::evaluables())->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ------------------------------------------------------------------
    // Reglas del flujo (usadas por las páginas para no saltarse pasos)
    // ------------------------------------------------------------------

    public static function verificacionDe(Solicitud $solicitud): ?SolicitudVerificacion
    {
        return SolicitudVerificacion::where('solicitud_id', $solicitud->id)
            ->where('evaluador_id', Auth::id())
            ->first();
    }

    /** Paso 1 completo: declaró no conflicto de interés y registró ubicación. */
    public static function filtroCompleto(Solicitud $solicitud): bool
    {
        $verificacion = static::verificacionDe($solicitud);

        return $verificacion?->conflicto_interes === true && $verificacion->lat !== null;
    }

    /** Paso 2 completo: guardó chip/fotos (estado en_progreso o posterior). */
    public static function campoCompleto(Solicitud $solicitud): bool
    {
        return in_array(static::verificacionDe($solicitud)?->estado, ['en_progreso', 'enviada'], true);
    }
}
