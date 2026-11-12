<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas web:
 * - nosniff: el navegador no adivina el tipo de un archivo (evita ejecutar un adjunto como script).
 * - SAMEORIGIN: la app no se puede incrustar en otro sitio (clickjacking).
 * - Referrer-Policy: los enlaces firmados (documentos privados) no se filtran a otros sitios.
 * - Permissions-Policy: cámara y ubicación solo para la propia app (fotos de campo); nada de micrófono ni pagos.
 * - HSTS: solo cuando ya se entra por https (producción), para forzar https en adelante.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff', false);
        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=()', false);

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $respuesta;
    }
}
