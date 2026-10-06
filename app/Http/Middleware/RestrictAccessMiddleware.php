<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe el acceso al panel por rol y tipo de dispositivo (configurable en
 * Administración → Configuración).
 *
 * Nota: la detección de celular usa el User-Agent, que el usuario puede falsear
 * ("ver como sitio de escritorio"). Sirve como política de uso, no como seguridad fuerte.
 */
class RestrictAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || $user->hasRole('admin')) {
            return $next($request);
        }

        $setting = SystemSetting::where('key', 'restrict_pc_access')->first();

        if (! $setting?->enabled) {
            return $next($request);
        }

        $rolesConfig = $setting->meta['roles'] ?? [];
        $config = collect($user->getRoleNames())
            ->map(fn ($rol) => $rolesConfig[$rol] ?? null)
            ->filter()
            ->first();

        if (! $config) {
            return $next($request);
        }

        if ($config['block_all'] ?? false) {
            return $this->expulsar($request, 'access.restricted');
        }

        if (($config['restrict_pc'] ?? false) && ! $this->esMovil($request)) {
            return $this->expulsar($request, 'access.restricted_pc');
        }

        return $next($request);
    }

    private function esMovil(Request $request): bool
    {
        $agente = strtolower((string) $request->userAgent());

        foreach (['mobile', 'android', 'iphone', 'ipad', 'ipod'] as $marca) {
            if (str_contains($agente, $marca)) {
                return true;
            }
        }

        return false;
    }

    private function expulsar(Request $request, string $ruta)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($ruta);
    }
}
