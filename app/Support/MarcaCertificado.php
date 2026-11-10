<?php

namespace App\Support;

use App\Models\SystemSetting;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

/**
 * Imágenes y firmante de los certificados en PDF. El admin los cambia en Configuración General ›
 * «Diseño de los certificados»; lo que no haya cargado sale con lo que trae la app por defecto.
 */
class MarcaCertificado
{
    /** imagen => archivo por defecto en public/images (si existe) */
    public const IMAGENES = [
        'logo' => 'logo-pdf.png',
        'onac' => 'onac.png',
        'sello' => 'sello.png',
        'marca_agua' => 'marca-agua.png',
        'firma' => 'firma-coordinador.png',
    ];

    public const FIRMANTE = 'JERSON GONGORA';

    public const CARGO = 'COORDINADOR DE TALLERES';

    /** Intensidad de la marca de agua, en porcentaje. Baja para que no tape el texto. */
    public const OPACIDAD = 5;

    public static function ajustes(): array
    {
        return SystemSetting::meta('certificados') ?? [];
    }

    /** Ruta en disco de la imagen (la cargada por el admin o la de la app), o null si no hay ninguna. */
    public static function imagen(string $cual): ?string
    {
        $cargada = self::ajustes()[$cual] ?? null;

        if (filled($cargada) && Storage::disk('local')->exists($cargada)) {
            return Storage::disk('local')->path($cargada);
        }

        $porDefecto = public_path('images/'.self::IMAGENES[$cual]);

        return file_exists($porDefecto) ? $porDefecto : null;
    }

    public static function firmante(): string
    {
        return self::ajustes()['firmante'] ?? self::FIRMANTE;
    }

    public static function cargo(): string
    {
        return self::ajustes()['cargo'] ?? self::CARGO;
    }

    /**
     * Nombre que sale como evaluador en el certificado. Con «Nombre evaluador» encendido en
     * Configuración General sale siempre el nombre fijo; apagado, el del evaluador que inspeccionó.
     */
    public static function nombreEvaluador(\App\Models\Solicitud $solicitud): string
    {
        $fijo = SystemSetting::meta('certificado_evaluador_fijo', 'nombre');

        return SystemSetting::enabled('certificado_evaluador_fijo') && filled($fijo)
            ? $fijo
            : ($solicitud->verificacion?->evaluador?->name ?? '—');
    }

    /** Opacidad CSS de la marca de agua (0.03 a 0.30). */
    public static function opacidad(): float
    {
        return min(30, max(3, (int) (self::ajustes()['opacidad'] ?? self::OPACIDAD))) / 100;
    }

    /** Código QR como imagen PNG incrustable en el PDF. */
    public static function qr(string $texto): string
    {
        return (new QRCode(new QROptions([
            'outputType' => 'png',
            'scale' => 6,
            'quietzoneSize' => 1,
            'outputBase64' => true,
        ])))->render($texto);
    }
}
