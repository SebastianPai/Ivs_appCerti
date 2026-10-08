<?php

namespace App\Models\Concerns;

use App\Models\Actividad;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en la tabla actividades cada creación, modificación y borrado del modelo.
 *
 * El modelo puede personalizar:
 *  - solicitudIdAuditoria(): a qué solicitud pertenece el registro (para la línea de tiempo)
 *  - describirAuditoria(string $evento, array $cambios): texto legible; devolver null para no registrar
 *  - eventoAuditoria(string $evento, array $cambios): evento más específico que creado/actualizado/eliminado
 *  - $noAuditar: columnas que no se guardan en el detalle de cambios
 */
trait Auditable
{
    /** Columnas que nunca se registran (secretos o ruido). */
    private static array $columnasOcultas = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
        'created_at', 'updated_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->registrarAuditoria('creado', $m->cambiosAuditables($m->getAttributes())));

        static::updated(function (Model $m) {
            $cambios = [];
            foreach ($m->cambiosAuditables($m->getChanges()) as $campo => $nuevo) {
                $cambios[$campo] = ['antes' => $m->getOriginal($campo), 'despues' => $nuevo];
            }

            if ($cambios !== []) {
                $m->registrarAuditoria('actualizado', $cambios);
            }
        });

        static::deleted(fn (Model $m) => $m->registrarAuditoria('eliminado', []));
    }

    protected function cambiosAuditables(array $atributos): array
    {
        $excluir = [...self::$columnasOcultas, ...($this->noAuditar ?? [])];

        return collect($atributos)
            ->except($excluir)
            ->map(fn ($v) => is_string($v) && mb_strlen($v) > 500 ? mb_substr($v, 0, 500).'…' : $v)
            ->all();
    }

    protected function registrarAuditoria(string $evento, array $cambios): void
    {
        $descripcion = $this->describirAuditoria($evento, $cambios);

        if ($descripcion === null) {
            return;
        }

        Actividad::registrar($this->eventoAuditoria($evento, $cambios), $descripcion, $this, $this->solicitudIdAuditoria(), $cambios ?: null);
    }

    /** Permite clasificar un cambio con un evento más específico (p. ej. "estado"). */
    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return $evento;
    }

    protected function solicitudIdAuditoria(): ?int
    {
        return $this->solicitud_id ?? null;
    }

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        $nombre = class_basename($this);

        return match ($evento) {
            'creado' => "{$nombre} #{$this->getKey()} creado",
            'eliminado' => "{$nombre} #{$this->getKey()} eliminado",
            default => "{$nombre} #{$this->getKey()} modificado: ".implode(', ', array_keys($cambios)),
        };
    }
}
