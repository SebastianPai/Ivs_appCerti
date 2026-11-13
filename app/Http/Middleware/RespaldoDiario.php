<?php

namespace App\Http\Middleware;

use App\Support\PasswordInicial;
use App\Support\RespaldoBase;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tareas que en otro hosting harían un cron (aquí no hay): el respaldo diario de la base, cuando
 * entra un administrador, y el cambio de la contraseña inicial publicada (ver PasswordInicial).
 */
class RespaldoDiario
{
    public function handle(Request $request, Closure $next): Response
    {
        PasswordInicial::rotarSiCorresponde();

        if ($request->isMethod('GET') && $request->user()?->hasRole('admin')) {
            if (! $request->session()->get('aviso_password_inicial') && PasswordInicial::hayQueAvisar()) {
                $request->session()->put('aviso_password_inicial', true);

                Notification::make()
                    ->title('Falta la contraseña inicial en el servidor')
                    ->body(PasswordInicial::pendientes().' cuenta(s) siguen con la contraseña inicial anterior, que quedó publicada. Agregue PASSWORD_INICIAL_USUARIOS en el .env del servidor y vuelva a publicar: esas cuentas pasarán a entrar con la nueva.')
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        $respuesta = $next($request);

        if ($request->isMethod('GET') && $request->user()?->hasRole('admin')) {
            RespaldoBase::siCorresponde();
        }

        return $respuesta;
    }
}
