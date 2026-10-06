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
}
