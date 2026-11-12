<?php

namespace App\Providers;

use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Models\Actividad;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Correos de la cuenta con el diseño y los textos de IVS (Configuración → Correos)
        $this->app->bind(\Filament\Auth\Notifications\VerifyEmail::class, \App\Notifications\Cuenta\BienvenidaConfirmarCorreo::class);
        $this->app->bind(\Filament\Auth\Notifications\ResetPassword::class, \App\Notifications\Cuenta\RestablecerContrasena::class);
        $this->app->bind(\Filament\Auth\Notifications\VerifyEmailChange::class, \App\Notifications\Cuenta\ConfirmarCambioCorreo::class);
        $this->app->bind(\Filament\Auth\Notifications\NoticeOfEmailChangeRequest::class, \App\Notifications\Cuenta\AvisoCambioCorreo::class);
    }

    public function boot(): void
    {
        // SMTP: validar el certificado del servidor con la cadena de MAIL_CAFILE (ver config/mail.php)
        $this->callAfterResolving('mail.manager', function (\Illuminate\Mail\MailManager $correo) {
            $cafile = config('mail.mailers.smtp.cafile');

            if (blank($cafile) || config('mail.default') !== 'smtp') {
                return;
            }

            $transporte = $correo->mailer('smtp')->getSymfonyTransport();

            if ($transporte instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport) {
                $ruta = str_starts_with($cafile, '/') || preg_match('/^[A-Za-z]:/', $cafile) ? $cafile : base_path($cafile);
                $transporte->getStream()->setStreamOptions(['ssl' => ['cafile' => $ruta]]);
            }
        });

        // Contraseñas: mínimo 8 caracteres con letras y números (perfil y «Restablecer contraseña» de Filament
        // usan Password::default(); es la misma regla de «Cambiar contraseña»)
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers());

        // Archivos privados (documentos, fotos, cámara de comercio): Filament y la app piden
        // enlaces temporales; se sirven por la ruta "archivos.privado", que exige sesión y firma.
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration) => URL::temporarySignedRoute('archivos.privado', $expiration, ['path' => $path])
        );

        // Auditoría de accesos
        Event::listen(Login::class, fn (Login $e) => Actividad::registrar('login', 'Inicio de sesión', userId: $e->user->getAuthIdentifier()));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Actividad::registrar('logout', 'Cierre de sesión', userId: $e->user->getAuthIdentifier()));
        Event::listen(Failed::class, fn (Failed $e) => Actividad::registrar(
            'login_fallido',
            'Intento de inicio de sesión fallido con el correo '.mb_substr((string) ($e->credentials['email'] ?? '?'), 0, 120),
            userId: $e->user?->getAuthIdentifier(),
        ));

        // Borrador automático de la solicitud: los campos de texto de Filament no se envían
        // al servidor mientras se escribe, así que cada 5 s (y al salir/ocultar la página)
        // se sincronizan para que CreateSolicitud::updated() guarde el borrador.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): string => Blade::render(<<<'BLADE'
                <div
                    x-data="{
                        timer: null,
                        sync() { $wire.$commit() },
                        init() {
                            this.timer = setInterval(() => document.visibilityState === 'visible' && this.sync(), 5000);
                        },
                        destroy() { clearInterval(this.timer) },
                    }"
                    x-on:visibilitychange.document="document.visibilityState === 'hidden' && sync()"
                    x-on:pagehide.window="sync()"
                    hidden
                ></div>
            BLADE),
            scopes: CreateSolicitud::class,
        );
    }
}
