<?php

namespace App\Filament\Pages;

use App\Models\Actividad;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Primera entrada con la contraseña inicial: el usuario elige la suya antes de usar la aplicación
 * (ver ExigirCambioPassword). Después la cambia cuando quiera desde su perfil.
 */
class CambiarPassword extends Page
{
    protected string $view = 'filament.pages.cambiar-password';

    protected static ?string $slug = 'cambiar-contrasena';

    protected static ?string $title = 'Cambiar contraseña';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function mount(): void
    {
        if (! Auth::user()->debe_cambiar_password) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bienvenido a la nueva plataforma')
                    ->description('Por seguridad, cambie la contraseña inicial por una que solo usted conozca.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(8)->letters()->numbers())
                            ->confirmed()
                            ->autocomplete('new-password'),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete('new-password'),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();
        $user = Auth::user();

        if (Hash::check($datos['password'], $user->password)) {
            $this->addError('data.password', 'Debe ser distinta de la contraseña inicial.');

            return;
        }

        $user->forceFill(['password' => $datos['password'], 'debe_cambiar_password' => false])->save();

        // AuthenticateSession cierra la sesión si cambia el hash: se guarda el nuevo para seguir dentro
        if (request()->hasSession()) {
            request()->session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
        }

        Actividad::registrar('seguridad', "{$user->name} cambió la contraseña inicial", $user);

        Notification::make()->title('Contraseña actualizada')->body('Ya puede usar la aplicación.')->success()->send();

        $this->redirect(Filament::getUrl());
    }
}
