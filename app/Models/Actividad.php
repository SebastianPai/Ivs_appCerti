<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría (tabla actividades). Solo se escribe, nunca se edita.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $solicitud_id
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string $evento
 * @property string $descripcion
 * @property array|null $cambios
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Actividad extends Model
{
    protected $table = 'actividades';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'solicitud_id',
        'auditable_type',
        'auditable_id',
        'evento',
        'descripcion',
        'cambios',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    /** Eventos que el taller puede ver en el historial de su solicitud. */
    public const EVENTOS_PUBLICOS = ['creado', 'estado'];

    public const EVENTOS = [
        'creado' => 'Creación',
        'actualizado' => 'Modificación',
        'eliminado' => 'Eliminación',
        'estado' => 'Cambio de estado',
        'inspeccion' => 'Inspección',
        'documento' => 'Documento',
        'sincronizacion' => 'Sincronización sin conexión',
        'aviso' => 'Aviso enviado',
        'login' => 'Inicio de sesión',
        'login_fallido' => 'Intento fallido',
        'logout' => 'Cierre de sesión',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function registrar(
        string $evento,
        string $descripcion,
        ?Model $sujeto = null,
        ?int $solicitudId = null,
        ?array $cambios = null,
        ?int $userId = null,
    ): self {
        $request = app()->runningInConsole() ? null : request();

        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'solicitud_id' => $solicitudId,
            'auditable_type' => $sujeto?->getMorphClass(),
            'auditable_id' => $sujeto?->getKey(),
            'evento' => $evento,
            'descripcion' => mb_substr($descripcion, 0, 5000),
            'cambios' => $cambios ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    public function eventoLabel(): string
    {
        return self::EVENTOS[$this->evento] ?? ucfirst($this->evento);
    }

    public function eventoColor(): string
    {
        return match ($this->evento) {
            'estado' => 'primary',
            'creado', 'login' => 'success',
            'eliminado', 'login_fallido' => 'danger',
            'inspeccion', 'sincronizacion' => 'info',
            'aviso' => 'warning',
            default => 'gray',
        };
    }
}
