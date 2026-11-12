<?php

namespace App\Notifications\Cuenta;

use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/** Usuario nuevo: bienvenida + confirmación del correo (reemplaza al de Filament, ver AppServiceProvider). */
class BienvenidaConfirmarCorreo extends VerifyEmail
{
    use ArmaCorreoCuenta;

    public function toMail($notifiable): MailMessage
    {
        return $this->correoCuenta($notifiable, 'cuenta_bienvenida', 'Bienvenida', 'Confirme su correo',
            $this->verificationUrl($notifiable), 'Confirmar mi correo',
            'Por seguridad, el enlace vence en '.$this->minutosDeVigencia('auth.verification.expire').' minutos. Si no esperaba este correo, puede ignorarlo.');
    }
}
