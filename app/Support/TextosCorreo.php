<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Textos editables de los correos (Configuración → Correos). Se guardan en el ajuste «correos»:
 *   meta.textos[tipo] = ['asunto' => ..., 'mensaje' => ...]
 *   meta.pie          = firma / datos de contacto al final de todos los correos
 *   meta.limite_hora  = máximo de correos del mismo tipo a un mismo destinatario por hora (0 = sin límite)
 *
 * En los textos se pueden usar {nombre} y {placa}. Lo que el admin deja vacío usa el texto de siempre.
 */
class TextosCorreo
{
    /** Correos de la cuenta (no se pueden apagar: sin ellos no se entra o no se recupera la cuenta). */
    public const CUENTA = [
        'cuenta_bienvenida' => [
            'nombre' => 'Bienvenida y confirmación de correo (usuario nuevo)',
            'asunto' => 'Bienvenido a IVS Certificaciones: confirme su correo',
            'mensaje' => "Le damos la bienvenida a la plataforma de IVS Certificaciones.\nPara activar su cuenta, confirme su correo con el botón de abajo.",
        ],
        'cuenta_password' => [
            'nombre' => 'Restablecer contraseña',
            'asunto' => 'Restablezca su contraseña de IVS Certificaciones',
            'mensaje' => 'Recibimos una solicitud para restablecer la contraseña de su cuenta. Use el botón de abajo para crear una nueva.',
        ],
        'cuenta_cambio_correo' => [
            'nombre' => 'Confirmar el correo nuevo (cambio de correo)',
            'asunto' => 'Confirme su nuevo correo en IVS Certificaciones',
            'mensaje' => 'Se pidió cambiar el correo de su cuenta a esta dirección. Confírmelo con el botón de abajo para que el cambio se aplique.',
        ],
        'cuenta_aviso_cambio' => [
            'nombre' => 'Aviso al correo anterior (cambio de correo)',
            'asunto' => 'Se pidió cambiar el correo de su cuenta',
            'mensaje' => 'Se pidió cambiar el correo de su cuenta de IVS Certificaciones. Si fue usted, no tiene que hacer nada. Si no lo reconoce, bloquee el cambio con el botón de abajo y cambie su contraseña.',
        ],
    ];

    public const LIMITE_HORA_POR_DEFECTO = 5;

    /** Tipos que nunca se limitan: el certificado aprobado es un documento que el destinatario necesita. */
    public const SIN_LIMITE = ['aprobacion'];

    public static function asunto(string $tipo, string $porDefecto, array $datos = []): string
    {
        $propio = trim((string) (self::textos()[$tipo]['asunto'] ?? ''));

        return self::reemplazar($propio !== '' ? $propio : (self::CUENTA[$tipo]['asunto'] ?? $porDefecto), $datos);
    }

    /** Mensaje del admin para un correo del flujo (va en un recuadro sobre el contenido), o el de la cuenta. */
    public static function mensaje(string $tipo, array $datos = []): ?string
    {
        $propio = trim((string) (self::textos()[$tipo]['mensaje'] ?? ''));
        $texto = $propio !== '' ? $propio : (self::CUENTA[$tipo]['mensaje'] ?? '');

        return $texto === '' ? null : self::reemplazar($texto, $datos);
    }

    public static function pie(): ?string
    {
        $pie = trim((string) (SystemSetting::meta('correos', 'pie') ?? ''));

        return $pie === '' ? null : $pie;
    }

    public static function limiteHora(): int
    {
        $limite = SystemSetting::meta('correos', 'limite_hora');

        return $limite === null ? self::LIMITE_HORA_POR_DEFECTO : max(0, (int) $limite);
    }

    /** @return array<string, array{asunto?: string, mensaje?: string}> */
    public static function textos(): array
    {
        return SystemSetting::meta('correos', 'textos') ?? [];
    }

    private static function reemplazar(string $texto, array $datos): string
    {
        $nombre = $datos['nombre'] ?? $datos['user_name'] ?? $datos['saludo'] ?? $datos['evaluador_name'] ?? '';

        return trim(strtr($texto, [
            '{nombre}' => (string) $nombre,
            '{placa}' => (string) ($datos['placa'] ?? ''),
        ]));
    }
}
