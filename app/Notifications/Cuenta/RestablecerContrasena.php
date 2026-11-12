<?php

namespace App\Notifications\Cuenta;

use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Restablecer contraseña (reemplaza al de Filament, ver AppServiceProvider). */
class RestablecerContrasena extends ResetPassword
{
    use ArmaCorreoCuenta;

    public function toMail($notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return $this->correoCuenta($notifiable, 'cuenta_password', 'Seguridad', 'Restablecer contraseña',
            $this->resetUrl($notifiable), 'Crear nueva contraseña',
            "El enlace vence en {$minutos} minutos y sirve una sola vez. Si usted no lo pidió, ignore este correo: su contraseña no cambia.");
    }
}
