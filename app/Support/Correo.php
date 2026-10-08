<?php

namespace App\Support;

use App\Jobs\EnviarCorreo;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Avisos del flujo de certificación.
 *
 * - Si el destinatario es un usuario, siempre le llega un aviso dentro de la app (campanita).
 * - El correo sale por la cola (no hace esperar al usuario) y solo si ese tipo de correo
 *   está activo en Configuración → Correos.
 */
class Correo
{
    /** Tipos de correo que el admin puede apagar uno por uno. */
    public const TIPOS = [
        'solicitud_creada' => 'Nueva solicitud (al taller y a sus evaluadores)',
        'documentos' => 'Documentos cargados o corregidos por el taller',
        'devolucion' => 'Devoluciones (al taller o al evaluador)',
        'revision' => 'Evaluación lista para revisión (al revisor)',
        'aprobacion' => 'Certificado aprobado (al taller)',
        'vencimiento' => 'Aviso de vencimiento del certificado (taller y propietario)',
    ];

    public static function enviar(User|string|null $para, string $tipo, string $asunto, string $vista, array $datos): void
    {
        if ($para instanceof User) {
            self::avisoEnApp($para, $asunto, $datos);
        }

        $email = $para instanceof User ? $para->email : $para;

        if (blank($email) || ! SystemSetting::correoActivo($tipo)) {
            return;
        }

        try {
            EnviarCorreo::dispatch($email, $asunto, $vista, $datos);
        } catch (\Throwable $e) {
            // Si la cola no está disponible, el flujo no se interrumpe
            Log::warning("No se pudo encolar el correo «{$asunto}» a {$email}: {$e->getMessage()}");
        }
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
