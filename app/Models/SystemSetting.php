<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property bool $enabled
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class SystemSetting extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'key',
        'enabled',
        'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'meta' => 'array',
    ];

    public static function enabled(string $key): bool
    {
        return (bool) self::where('key', $key)->value('enabled');
    }

    /**
     * Ojo: se usa first() y no value('meta') porque value() se salta el cast
     * y devuelve el JSON como string.
     */
    public static function meta(string $key, ?string $metaKey = null): mixed
    {
        $meta = self::where('key', $key)->first()?->meta;

        if (! is_array($meta)) {
            return null;
        }

        return $metaKey ? ($meta[$metaKey] ?? null) : $meta;
    }

    public static function put(string $key, bool $enabled, ?array $meta = null): self
    {
        return self::updateOrCreate(['key' => $key], ['enabled' => $enabled, 'meta' => $meta]);
    }

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        return "Configuración «{$this->key}» ".($evento === 'creado' ? 'establecida' : 'modificada');
    }

    /** Como enabled(), pero con un valor por defecto cuando el ajuste nunca se ha guardado. */
    public static function enabledOr(string $key, bool $default): bool
    {
        $valor = self::where('key', $key)->value('enabled');

        return $valor === null ? $default : (bool) $valor;
    }

    /** Vigencia del certificado y anticipación del aviso de vencimiento. */
    public static function vigencia(): array
    {
        $meta = self::meta('vigencia') ?? [];

        return [
            'meses' => max(1, (int) ($meta['meses'] ?? 12)),
            'dias_aviso' => max(1, (int) ($meta['dias_aviso'] ?? 30)),
        ];
    }

    /**
     * ¿Se envía este tipo de correo? Por defecto todos están activos.
     * El interruptor general ("correos") apaga todos; meta.desactivados apaga tipos sueltos.
     */
    public static function correoActivo(string $tipo): bool
    {
        $ajuste = self::where('key', 'correos')->first();

        if (! $ajuste) {
            return true;
        }

        return $ajuste->enabled && ! in_array($tipo, $ajuste->meta['desactivados'] ?? [], true);
    }

    /** ¿Este usuario debe tener activa la verificación en dos pasos? */
    public static function exige2fa(?User $user): bool
    {
        if (! $user || ! self::enabled('exigir_2fa')) {
            return false;
        }

        return $user->hasAnyRole(self::meta('exigir_2fa', 'roles') ?? []);
    }
}
