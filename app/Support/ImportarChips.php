<?php

namespace App\Support;

use App\Models\Chip;
use Illuminate\Support\Facades\DB;

/**
 * Importa los chips de la plataforma anterior (Admin Chips). Acepta:
 *  - JSON: lista de objetos tal como los entrega su API (AddChip/getChip), con el código en
 *    idChip / chipid / idrom / codigo / chip / id_chip, y el estado en estado / state / activo /
 *    idEstado / nombreEstado (1, «ACTIVO», true…). También { "result": [...] } o { "data": [...] }.
 *  - Texto: un código por línea (quedan activos).
 *
 * Solo crea los que faltan. Un chip que ya existe no se toca (no se reactiva uno que ya se usó aquí).
 * Se puede repetir sin duplicar.
 */
class ImportarChips
{
    private const CAMPOS_CODIGO = ['idchip', 'chipid', 'idrom', 'codigo', 'chip', 'id_chip', 'codigochip', 'idrom_chip'];

    private const CAMPOS_ESTADO = ['estado', 'state', 'activo', 'idestado', 'nombreestado', 'estadochip', 'status'];

    /** @return array{creados: int, existentes: int, omitidos: list<string>} */
    public static function desdeTexto(string $contenido): array
    {
        $contenido = trim(preg_replace('/^\xEF\xBB\xBF/', '', $contenido));
        $json = json_decode($contenido, true);

        if (is_array($json)) {
            return self::desde($json);
        }

        $filas = collect(preg_split('/\R/', $contenido))
            ->map(fn ($linea) => trim(explode(',', (string) $linea)[0], " \t\"'"))
            ->filter()
            ->map(fn ($codigo) => ['codigo' => $codigo])
            ->all();

        return self::desde($filas);
    }

    /** @return array{creados: int, existentes: int, omitidos: list<string>} */
    public static function desde(array $datos): array
    {
        $filas = $datos['result'] ?? $datos['data'] ?? $datos['chips'] ?? $datos;
        $resumen = ['creados' => 0, 'existentes' => 0, 'omitidos' => []];

        if (! is_array($filas) || ! array_is_list($filas)) {
            throw new \InvalidArgumentException('El archivo no trae una lista de chips.');
        }

        DB::transaction(function () use ($filas, &$resumen) {
            foreach ($filas as $i => $fila) {
                $fila = is_array($fila) ? array_change_key_case($fila, CASE_LOWER) : ['codigo' => $fila];
                $codigo = strtoupper(trim((string) self::primero($fila, self::CAMPOS_CODIGO)));

                if (! preg_match('/^[0-9A-Z]{4,100}$/', $codigo)) {
                    $resumen['omitidos'][] = 'fila '.($i + 1).($codigo !== '' ? " ({$codigo})" : '').': código no válido';

                    continue;
                }

                if (Chip::whereRaw('UPPER(codigo) = ?', [$codigo])->exists()) {
                    $resumen['existentes']++;

                    continue;
                }

                Chip::create([
                    'codigo' => $codigo,
                    'activo' => self::activo(self::primero($fila, self::CAMPOS_ESTADO)),
                    'descripcion' => 'Importado de la plataforma anterior',
                ]);
                $resumen['creados']++;
            }
        });

        return $resumen;
    }

    private static function primero(array $fila, array $campos): mixed
    {
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $fila) && $fila[$campo] !== null && $fila[$campo] !== '') {
                return $fila[$campo];
            }
        }

        return null;
    }

    /** Sin estado = activo. Inactivo: 0, false, «INACTIVO», «USADO», «ASIGNADO», «ANULADO»… */
    private static function activo(mixed $estado): bool
    {
        if ($estado === null) {
            return true;
        }

        if (is_bool($estado) || is_int($estado)) {
            return (bool) $estado;
        }

        $texto = mb_strtoupper(trim((string) $estado));

        return ! in_array($texto, ['0', 'FALSE', 'NO', 'INACTIVO', 'INACTIVE', 'USADO', 'ASIGNADO', 'ANULADO', 'DADO DE BAJA', 'BAJA'], true);
    }
}
