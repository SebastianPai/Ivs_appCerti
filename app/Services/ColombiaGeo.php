<?php

namespace App\Services;

/**
 * Departamentos y municipios de Colombia desde database/data/colombia.json
 * (descargado de api-colombia.com). Se lee del disco y no de internet: la llamada
 * a la API fallaba con XAMPP (sin certificados SSL) y además hacía lento el
 * formulario, porque se repetía en cada cambio de un campo.
 *
 * Las opciones usan el NOMBRE como clave, así lo que se guarda en BD es legible.
 */
class ColombiaGeo
{
    /** @var array<int, array{id:int,nombre:string,ciudades:array<int,string>}>|null */
    private static ?array $datos = null;

    private static function datos(): array
    {
        return self::$datos ??= json_decode(
            file_get_contents(database_path('data/colombia.json')),
            true,
        );
    }

    /** @return array<string, string> nombre => nombre */
    public static function departamentos(): array
    {
        return collect(self::datos())->mapWithKeys(fn ($d) => [$d['nombre'] => $d['nombre']])->all();
    }

    /** @return array<string, string> nombre => nombre */
    public static function ciudades(?string $departamento): array
    {
        if (! $departamento) {
            return [];
        }

        // Compatibilidad con registros viejos que guardaron el id numérico de la API
        $dep = collect(self::datos())->first(
            fn ($d) => $d['nombre'] === $departamento || (string) $d['id'] === $departamento
        );

        return $dep ? array_combine($dep['ciudades'], $dep['ciudades']) : [];
    }
}
