<?php

namespace App\Http\Middleware;

use App\Support\RespaldoBase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Dispara el respaldo diario de la base cuando un administrador entra (no hay cron en el hosting). */
class RespaldoDiario
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if ($request->isMethod('GET') && $request->user()?->hasRole('admin')) {
            RespaldoBase::siCorresponde();
        }

        return $respuesta;
    }
}
