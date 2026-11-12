<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;

/**
 * Perfil: nombre, contraseña y verificación en dos pasos (la sección del 2FA la agrega Filament).
 * El correo solo lo cambia el administrador, porque es la cuenta a la que llegan los avisos.
 * Se presenta en tarjetas: cabecera con avatar y rol, datos de la cuenta y contraseña.
 */
class EditarPerfil extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.auth.perfil-cabecera'),
            Section::make('Datos de la cuenta')
                ->icon('heroicon-o-user-circle')
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ]),
            Section::make('Contraseña')
                ->icon('heroicon-o-key')
                ->description('Déjela vacía para conservar la actual. Mínimo 8 caracteres, con letras y números.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email
            ->disabled(fn () => ! Auth::user()?->hasRole('admin'))
            ->helperText(fn () => Auth::user()?->hasRole('admin') ? null : 'Para cambiar el correo, pídaselo al administrador.');
    }
}
