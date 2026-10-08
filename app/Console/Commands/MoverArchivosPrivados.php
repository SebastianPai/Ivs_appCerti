<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve los documentos y fotos del disco público (accesibles por cualquiera con la URL)
 * al disco privado, con la misma ruta relativa, así la base de datos no cambia.
 */
class MoverArchivosPrivados extends Command
{
    protected $signature = 'ivs:archivos-privados {--simular : Solo muestra qué se movería}';

    protected $description = 'Mueve documentos, fotos y cámaras de comercio del disco público al privado';

    /** Carpetas del disco público que contienen documentos de usuarios. */
    private const CARPETAS = ['solicitudes', 'evaluadores', 'camara-comercio'];

    public function handle(): int
    {
        $publico = Storage::disk('public');
        $privado = Storage::disk('local');
        $simular = (bool) $this->option('simular');
        $movidos = 0;
        $conflictos = 0;

        foreach (self::CARPETAS as $carpeta) {
            foreach ($publico->allFiles($carpeta) as $ruta) {
                if ($privado->exists($ruta)) {
                    $this->warn("Ya existe en privado, se deja sin tocar: {$ruta}");
                    $conflictos++;

                    continue;
                }

                if (! $simular) {
                    $privado->writeStream($ruta, $publico->readStream($ruta));
                    $publico->delete($ruta);
                }

                $movidos++;
            }

            if (! $simular) {
                foreach ([...array_reverse($publico->allDirectories($carpeta)), $carpeta] as $dir) {
                    if ($publico->allFiles($dir) === []) {
                        $publico->deleteDirectory($dir);
                    }
                }
            }
        }

        $this->info(($simular ? 'Se moverían' : 'Movidos').": {$movidos} archivo(s). Conflictos: {$conflictos}.");

        return self::SUCCESS;
    }
}
