<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;

/**
 * Perfil: nombre, contraseña y verificación en dos pasos (la sección del 2FA la agrega Filament).
 * El correo solo lo cambia el administrador, porque es la cuenta a la que llegan los avisos.
 */
class EditarPerfil extends EditProfile
{
    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email
            ->disabled(fn () => ! Auth::user()?->hasRole('admin'))
            ->helperText(fn () => Auth::user()?->hasRole('admin') ? null : 'Para cambiar el correo, pídaselo al administrador.');
    }
}
