<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pantalla de ingreso con diseño propio: tarjeta con el formulario a la izquierda y una
 * ilustración a la derecha. La lógica (rate limit, 2FA, recordar sesión) es la de Filament.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.layouts.login';

    public function getHeading(): string | Htmlable | null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? 'Verificación en dos pasos'
            : 'Ingresa a tu cuenta';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : null;
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->hiddenLabel()
            ->placeholder('Correo electrónico');
    }

    // El enlace «¿Olvidaste tu contraseña?» va junto al botón (ver la vista), no encima del campo
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->hiddenLabel()
            ->hint(null)
            ->placeholder('Contraseña');
    }

    /** Como en el diseño: «¿Olvidaste tu contraseña?» a la izquierda y «Ingresar» a la derecha. */
    protected function getFormActions(): array
    {
        return array_filter([
            filament()->hasPasswordReset()
                ? Action::make('olvidePassword')
                    ->label('¿Olvidaste tu contraseña?')
                    ->link()
                    ->color('gray')
                    ->url(filament()->getRequestPasswordResetUrl())
                    ->extraAttributes(['class' => 'ivs-login__olvide'])
                : null,
            $this->getAuthenticateFormAction()->label('Ingresar'),
        ]);
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::Between;
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    protected function hasFullWidthMultiFactorChallengeFormActions(): bool
    {
        return true;
    }
}
