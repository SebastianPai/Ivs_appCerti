<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Departamentos y ciudades desde api-colombia.com, cacheados para no llamar a la API
 * en cada render del formulario (antes cada tecla en un campo "live" hacía peticiones HTTP).
 *
 * Las opciones usan el NOMBRE como clave, así lo que se guarda en BD es legible
 * (antes se guardaba el id de la API, p.ej. "769", y eso se mostraba al evaluador).
 */
class ColombiaGeo
{
    private const BASE = 'https://api-colombia.com/api/v1';

    /** @return array<int, array{id:int,name:string}> */
    private static function departamentosRaw(): array
    {
        return self::rememberIfNotEmpty('colombia_departamentos', fn () => self::get('/Department'));
    }

    /** @return array<string, string> nombre => nombre */
    public static function departamentos(): array
    {
        return collect(self::departamentosRaw())
            ->pluck('name')
            ->sort()
            ->mapWithKeys(fn ($n) => [$n => $n])
            ->all();
    }

    /** @return array<string, string> nombre => nombre */
    public static function ciudades(?string $departamento): array
    {
        if (! $departamento) {
            return [];
        }

        // Compatibilidad con registros viejos que guardaron el id numérico
        $dep = collect(self::departamentosRaw())->first(
            fn ($d) => $d['name'] === $departamento || (string) $d['id'] === $departamento
        );

        if (! $dep) {
            return [];
        }

        $ciudades = self::rememberIfNotEmpty(
            "colombia_ciudades_{$dep['id']}",
            fn () => self::get("/Department/{$dep['id']}/cities")
        );

        return collect($ciudades)
            ->pluck('name')
            ->sort()
            ->mapWithKeys(fn ($n) => [$n => $n])
            ->all();
    }

    private static function get(string $path): array
    {
        try {
            $response = Http::timeout(6)->acceptJson()->get(self::BASE.$path);

            return $response->successful() ? (array) $response->json() : [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** No cachear respuestas vacías (si la API falla, se reintenta en la próxima carga). */
    private static function rememberIfNotEmpty(string $key, \Closure $callback): array
    {
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $value = $callback();

        if (! empty($value)) {
            Cache::put($key, $value, now()->addDays(30));
        }

        return $value;
    }
}
