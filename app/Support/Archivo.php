<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Documentos y fotos viven en el disco privado ("local", storage/app/private).
 * Los archivos subidos antes de octubre 2026 pueden seguir en el disco "public"
 * hasta que se ejecute `php artisan ivs:archivos-privados`.
 */
class Archivo
{
    public const DISCO = 'local';

    /** Minutos que dura un enlace a un archivo privado. */
    public const MINUTOS_ENLACE = 30;

    /** Enlace para ver el archivo, o null si no existe. */
    public static function url(?string $ruta): ?string
    {
        if (blank($ruta)) {
            return null;
        }

        if (Storage::disk(self::DISCO)->exists($ruta)) {
            return Storage::disk(self::DISCO)->temporaryUrl($ruta, now()->addMinutes(self::MINUTOS_ENLACE));
        }

        // Archivo antiguo que aún no se ha movido al disco privado
        return Storage::disk('public')->exists($ruta) ? Storage::disk('public')->url($ruta) : null;
    }
}
