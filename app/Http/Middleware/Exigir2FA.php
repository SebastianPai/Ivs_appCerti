<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Obliga a configurar la verificación en dos pasos solo a los roles elegidos en
 * Configuración → Seguridad. Los demás usuarios pueden activarla de forma opcional desde su perfil.
 */
class Exigir2FA extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! SystemSetting::exige2fa(Filament::auth()->user())) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
