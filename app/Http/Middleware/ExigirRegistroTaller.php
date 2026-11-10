<?php

namespace App\Http\Middleware;

use App\Filament\Pages\RegistroTaller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Un taller con el registro de Industria y Comercio vencido o sin fecha solo puede entrar a la
 * pantalla donde lo carga, como en la plataforma anterior (ver User::debeActualizarRegistro).
 */
class ExigirRegistroTaller
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->isMethod('GET')
            // Primero se confirma el correo; el registro se pide después
            && Auth::user()?->hasVerifiedEmail()
            && Auth::user()->debeActualizarRegistro()
            && ! $request->routeIs(RegistroTaller::getRouteName(), \App\Filament\Pages\Contactenos::getRouteName(), 'filament.admin.auth.*')
        ) {
            return redirect(RegistroTaller::getUrl());
        }

        return $next($request);
    }
}
