<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Copia de los archivos en Firebase Cloud Storage, como la plataforma anterior (Adminstorage):
 * cada archivo se guarda en el servidor y además en Firebase, organizado por solicitud.
 *
 *   solicitudes/{n.º}/documentos/…   documentos que sube el taller
 *   solicitudes/{n.º}/fotos/…        fotos del evaluador
 *   talleres/camara-comercio/…       registros de Industria y Comercio
 *   configuracion/…                  firmas y sellos de los certificados
 *   respaldos/base-de-datos/…        copia diaria de la base (ver RespaldoBase)
 *
 * Si un archivo falta en el servidor, la app lo sirve desde Firebase (Archivo::url).
 */
class Nube
{
    public const DISCO = 'firebase';

    /** Hay credenciales y bucket en el .env del servidor. */
    public static function configurada(): bool
    {
        return filled(config('filesystems.disks.firebase.bucket'))
            && (filled(config('filesystems.disks.firebase.key_file')) || filled(config('filesystems.disks.firebase.key_file_path')));
    }

    /** Configurada y encendida en Configuración General (activa por defecto). */
    public static function activa(): bool
    {
        return self::configurada() && SystemSetting::enabledOr('copia_firebase', true);
    }

    /** Carpeta en Firebase que corresponde a un archivo del disco privado. */
    public static function rutaRemota(string $local): string
    {
        $local = ltrim($local, '/');

        return match (true) {
            (bool) preg_match('#^solicitudes/(\d+)/adjuntos/(.+)$#', $local, $m) => "solicitudes/{$m[1]}/documentos/{$m[2]}",
            (bool) preg_match('#^evaluadores/(\d+)/fotos/(.+)$#', $local, $m) => "solicitudes/{$m[1]}/fotos/{$m[2]}",
            str_starts_with($local, 'camara-comercio/') => 'talleres/'.$local,
            str_starts_with($local, 'certificados-marca/') => 'configuracion/'.$local,
            default => 'otros/'.$local,
        };
    }

    /**
     * Sube a Firebase un archivo del disco privado. No interrumpe al usuario si falla: queda en
     * el log y se recupera con «Copiar archivos pendientes».
     */
    public static function copiar(?string $local): bool
    {
        if (blank($local) || ! self::activa()) {
            return false;
        }

        try {
            $disco = Storage::disk(Archivo::DISCO);

            if (! $disco->exists($local)) {
                return false;
            }

            $flujo = $disco->readStream($local);
            Storage::disk(self::DISCO)->writeStream(self::rutaRemota($local), $flujo);

            if (is_resource($flujo)) {
                fclose($flujo);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo copiar a Firebase', ['archivo' => $local, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Copia después de responder, para que el usuario no espere la subida. */
    public static function copiarDespues(?string $local): void
    {
        if (filled($local) && self::activa()) {
            defer(fn () => self::copiar($local));
        }
    }

    /** Enlace temporal al respaldo en Firebase, para un archivo que falta en el servidor. */
    public static function url(string $local, \DateTimeInterface $vence): ?string
    {
        if (! self::activa()) {
            return null;
        }

        try {
            $remota = self::rutaRemota($local);

            return Storage::disk(self::DISCO)->exists($remota) ? Storage::disk(self::DISCO)->temporaryUrl($remota, $vence) : null;
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer de Firebase', ['archivo' => $local, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Sube lo que aún no está en Firebase. Trabaja por tandas (límite de tiempo) para no pasar
     * el tiempo máximo de una petición en el hosting; se repite hasta que no quede nada.
     *
     * @return array{copiados: int, pendientes: int, errores: int}
     */
    public static function sincronizar(int $segundos = 20): array
    {
        $resumen = ['copiados' => 0, 'pendientes' => 0, 'errores' => 0];

        if (! self::activa()) {
            return $resumen;
        }

        $inicio = microtime(true);
        $remoto = Storage::disk(self::DISCO);

        foreach (Storage::disk(Archivo::DISCO)->allFiles() as $local) {
            if (str_starts_with($local, 'importaciones/') || str_starts_with($local, 'livewire-tmp/')) {
                continue; // temporales
            }

            if (microtime(true) - $inicio > $segundos) {
                $resumen['pendientes']++;

                continue;
            }

            if ($remoto->exists(self::rutaRemota($local))) {
                continue;
            }

            self::copiar($local) ? $resumen['copiados']++ : $resumen['errores']++;
        }

        return $resumen;
    }
}
