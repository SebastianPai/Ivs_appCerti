<?php

namespace App\Support;

use App\Jobs\EnviarCorreo;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Avisos del flujo de certificación.
 *
 * - Si el destinatario es un usuario, siempre le llega un aviso dentro de la app (campanita).
 * - El correo sale por la cola (no hace esperar al usuario) y solo si ese tipo de correo
 *   está activo en Configuración → Correos.
 * - Antispam: un mismo destinatario recibe como máximo N correos del mismo tipo por hora
 *   (Configuración → Correos; p. ej. un taller que crea 100 solicitudes seguidas). Lo que pasa
 *   del límite no sale por correo, pero sí queda en la campanita. El certificado siempre sale.
 * - Asunto, mensaje adicional y pie se pueden editar en Configuración (ver TextosCorreo).
 */
class Correo
{
    /** Tipos de correo que el admin puede apagar uno por uno. */
    public const TIPOS = [
        'solicitud_creada' => 'Nueva solicitud (al taller y a sus evaluadores)',
        'documentos' => 'Documentos cargados o corregidos por el taller',
        'devolucion' => 'Devoluciones (al taller o al evaluador)',
        'revision' => 'Evaluación lista para revisión (al revisor)',
        'aprobacion' => 'Vehículo certificado, con el certificado adjunto (al taller y al propietario)',
        'inspector' => 'Evaluador asignado o desvinculado (al taller)',
        'vencimiento' => 'Aviso de vencimiento del certificado (taller y propietario)',
    ];

    public static function enviar(User|string|null $para, string $tipo, string $asunto, string $vista, array $datos, ?int $certificadoDe = null): void
    {
        // Un usuario desactivado no recibe nada (ni correo ni campanita)
        if ($para instanceof User && $para->activo === false) {
            return;
        }

        if ($para instanceof User) {
            self::avisoEnApp($para, $asunto, $datos);
        }

        $email = $para instanceof User ? $para->email : $para;

        if (blank($email) || ! SystemSetting::correoActivo($tipo)) {
            return;
        }

        if (self::superaLimite($email, $tipo)) {
            Log::info("Correo «{$asunto}» a {$email} no enviado: superó el límite por hora del tipo «{$tipo}».");

            return;
        }

        $asunto = TextosCorreo::asunto($tipo, $asunto, $datos);
        $datos['mensaje_admin'] = TextosCorreo::mensaje($tipo, $datos);
        $datos['pie_correo'] = TextosCorreo::pie();

        try {
            EnviarCorreo::dispatch($email, $asunto, $vista, $datos, $certificadoDe);
        } catch (\Throwable $e) {
            // Si la cola no está disponible, el flujo no se interrumpe
            Log::warning("No se pudo encolar el correo «{$asunto}» a {$email}: {$e->getMessage()}");
        }
    }

    /** Cuenta el envío y dice si el destinatario ya recibió el máximo de este tipo en la última hora. */
    private static function superaLimite(string $email, string $tipo): bool
    {
        $limite = TextosCorreo::limiteHora();

        if ($limite === 0 || in_array($tipo, TextosCorreo::SIN_LIMITE, true)) {
            return false;
        }

        $clave = 'correo:'.$tipo.':'.sha1(strtolower(trim($email)));

        if (RateLimiter::tooManyAttempts($clave, $limite)) {
            return true;
        }

        RateLimiter::hit($clave, 3600);

        return false;
    }

    private static function avisoEnApp(User $user, string $asunto, array $datos): void
    {
        $url = $datos['boton_url'] ?? $datos['url_gestion'] ?? $datos['url_solicitud'] ?? $datos['url_adjuntos'] ?? null;

        try {
            $aviso = Notification::make()
                ->title(trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $asunto)))
                ->body($datos['detalle'] ?? $datos['observacion'] ?? null)
                ->icon('heroicon-o-bell')
                ->actions($url ? [Action::make('abrir')->label('Abrir')->url($url)->markAsRead()] : []);

            // Inmediato (sin cola): el aviso dentro de la app no depende del procesador de la cola
            $user->notifyNow($aviso->toDatabase());
        } catch (\Throwable $e) {
            Log::warning("No se pudo guardar el aviso «{$asunto}» para {$user->email}: {$e->getMessage()}");
        }
    }
}
