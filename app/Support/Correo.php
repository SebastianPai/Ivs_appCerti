<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío de correos sin romper el flujo: si el SMTP falla, se registra en el log
 * (storage/logs/laravel.log) en vez de tragarse el error en silencio.
 */
class Correo
{
    public static function enviar(?string $para, string $asunto, string $vista, array $datos): void
    {
        if (blank($para)) {
            return;
        }

        try {
            Mail::send($vista, $datos, fn ($message) => $message->to($para)->subject($asunto));
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar el correo «{$asunto}» a {$para}: {$e->getMessage()}");
        }
    }
}
