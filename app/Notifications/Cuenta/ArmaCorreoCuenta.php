<?php

namespace App\Notifications\Cuenta;

use App\Support\TextosCorreo;
use Illuminate\Notifications\Messages\MailMessage;

/** Arma los correos de la cuenta con la plantilla de IVS y los textos editables de Configuración. */
trait ArmaCorreoCuenta
{
    protected function correoCuenta(object $notifiable, string $tipo, string $etiqueta, string $titulo, string $url, string $boton, string $nota): MailMessage
    {
        $datos = ['nombre' => $notifiable->name ?? null];

        return (new MailMessage)
            ->subject(TextosCorreo::asunto($tipo, TextosCorreo::CUENTA[$tipo]['asunto'], $datos))
            ->view('emails.cuenta', [
                'etiqueta' => $etiqueta,
                'titulo' => $titulo,
                'nombre' => $datos['nombre'],
                'mensaje' => TextosCorreo::mensaje($tipo, $datos),
                'boton_url' => $url,
                'boton_texto' => $boton,
                'nota' => $nota,
                'pie_correo' => TextosCorreo::pie(),
            ]);
    }

    protected function minutosDeVigencia(string $config, int $porDefecto = 60): int
    {
        return (int) config($config, $porDefecto);
    }
}
