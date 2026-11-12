<?php

namespace App\Notifications\Cuenta;

use Filament\Auth\Notifications\VerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;

/** Cambio de correo: confirmación que llega al correo nuevo (reemplaza al de Filament, ver AppServiceProvider). */
class ConfirmarCambioCorreo extends VerifyEmailChange
{
    use ArmaCorreoCuenta;

    public function toMail($notifiable): MailMessage
    {
        return $this->correoCuenta($notifiable, 'cuenta_cambio_correo', 'Cambio de correo', 'Confirme su nuevo correo',
            $this->verificationUrl($notifiable), 'Confirmar el nuevo correo',
            'Hasta que lo confirme, los avisos siguen llegando al correo anterior. Si usted no pidió este cambio, ignore este mensaje.');
    }
}
