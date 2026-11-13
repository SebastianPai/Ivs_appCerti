<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Copia de seguridad de la base de datos en Firebase (respaldos/base-de-datos/). Se hace sola una
 * vez al día cuando un administrador usa la app (el hosting no tiene tareas programadas) y a
 * mano desde Configuración General. Se guardan los últimos DIAS días.
 */
class RespaldoBase
{
    public const CARPETA = 'respaldos/base-de-datos';

    public const DIAS = 30;

    /** Tablas que no hace falta respaldar: se regeneran solas. */
    private const OMITIR = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    /** Crea el respaldo y lo sube. Devuelve la ruta en Firebase. */
    public static function subir(): string
    {
        $ruta = self::CARPETA.'/'.now()->format('Y-m-d_His').'.sql.gz';
        Storage::disk(Nube::DISCO)->put($ruta, gzencode(self::volcado(), 9));

        Cache::forever('respaldo_base_ultimo', now()->toIso8601String());
        self::podar();

        return $ruta;
    }

    /** Respaldo automático: como mucho uno al día, después de responder al usuario. */
    public static function siCorresponde(): void
    {
        if (! Nube::activa() || Cache::has('respaldo_base_hoy')) {
            return;
        }

        Cache::put('respaldo_base_hoy', true, now()->addDay());

        defer(function () {
            try {
                self::subir();
            } catch (\Throwable $e) {
                Cache::forget('respaldo_base_hoy');
                Log::error('Falló el respaldo diario de la base', ['error' => $e->getMessage()]);
            }
        });
    }

    /** @return list<array{ruta: string, fecha: Carbon, tamano: int}> los más recientes primero */
    public static function lista(): array
    {
        $disco = Storage::disk(Nube::DISCO);

        return collect($disco->files(self::CARPETA))
            ->filter(fn ($r) => str_ends_with($r, '.sql.gz'))
            ->map(fn ($r) => ['ruta' => $r, 'fecha' => Carbon::createFromFormat('Y-m-d_His', basename($r, '.sql.gz')), 'tamano' => $disco->size($r)])
            ->sortByDesc('fecha')
            ->values()
            ->all();
    }

    private static function podar(): void
    {
        foreach (array_slice(self::lista(), self::DIAS) as $viejo) {
            Storage::disk(Nube::DISCO)->delete($viejo['ruta']);
        }
    }

    /** SQL con la estructura y los datos, sin depender de mysqldump (el hosting puede no tenerlo). */
    public static function volcado(): string
    {
        $pdo = DB::connection()->getPdo();
        $mysql = DB::connection()->getDriverName() === 'mysql' || DB::connection()->getDriverName() === 'mariadb';
        $sql = "-- Respaldo IVS Certificaciones · ".now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach (DB::connection()->getSchemaBuilder()->getTableListing(schemaQualified: false) as $tabla) {
            if (in_array($tabla, self::OMITIR, true)) {
                continue;
            }

            if ($mysql) {
                $crear = (array) DB::selectOne("SHOW CREATE TABLE `{$tabla}`");
                $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n".end($crear).";\n";
            }

            foreach (DB::table($tabla)->cursor() as $fila) {
                $valores = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $fila);
                $sql .= "INSERT INTO `{$tabla}` (`".implode('`,`', array_keys((array) $fila)).'`) VALUES ('.implode(',', $valores).");\n";
            }

            $sql .= "\n";
        }

        return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
