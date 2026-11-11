<?php

namespace App\Http\Middleware;

use App\Filament\Pages\CambiarPassword;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Quien entra con la contraseña inicial (usuarios traídos de la plataforma anterior) solo puede
 * ver la pantalla para cambiarla. Va antes que el registro del taller y que el 2FA.
 */
class ExigirCambioPassword
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->isMethod('GET')
            && Auth::user()?->debe_cambiar_password
            && ! $request->routeIs(CambiarPassword::getRouteName(), 'filament.admin.auth.*')
        ) {
            return redirect(CambiarPassword::getUrl());
        }

        return $next($request);
    }
}
