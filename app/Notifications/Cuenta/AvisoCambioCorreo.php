<?php

namespace App\Notifications\Cuenta;

use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Illuminate\Notifications\Messages\MailMessage;

/** Cambio de correo: aviso al correo anterior con la opción de bloquearlo (reemplaza al de Filament). */
class AvisoCambioCorreo extends NoticeOfEmailChangeRequest
{
    use ArmaCorreoCuenta;

    public function toMail(object $notifiable): MailMessage
    {
        return $this->correoCuenta($notifiable, 'cuenta_aviso_cambio', 'Seguridad', 'Cambio de correo solicitado',
            $this->blockVerificationUrl, 'No fui yo: bloquear el cambio',
            "Correo nuevo solicitado: {$this->newEmail}");
    }
}
