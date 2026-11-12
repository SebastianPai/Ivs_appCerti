<?php

namespace App\Console\Commands;

use App\Http\Controllers\VistaPreviaCorreosController;
use Illuminate\Console\Command;

/** Envía un correo de prueba de cada tipo (con datos de ejemplo) para revisar el diseño en un buzón real. */
class CorreosPrueba extends Command
{
    protected $signature = 'ivs:correos-prueba {email : Correo que recibe las pruebas}';

    protected $description = 'Envía un correo de prueba de cada tipo a la dirección indicada';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo no válido.');

            return self::FAILURE;
        }

        $n = VistaPreviaCorreosController::enviarPruebas($email);
        $this->info("{$n} correos de prueba enviados a {$email} (mailer: ".config('mail.default').').');

        return self::SUCCESS;
    }
}
