<?php

namespace App\Enums;

/**
 * Flujo de una solicitud de certificación:
 *
 *  Taller crea ──► PENDIENTE ──(evaluador devuelve)──► DEVUELTA_TALLER ──(taller corrige)──► SUBSANADA
 *                     │                                                                          │
 *                     └───────────────(evaluador finaliza checklist)◄────────────────────────────┘
 *                                              │
 *                                          EVALUADA ──(revisor devuelve)──► CORRECCION_TECNICA ──► EVALUADA
 *                                              │
 *                                   (revisor aprueba) ──► APROBADA  (certificado disponible)
 *
 *  Salidas sin certificado: CANCELADA (la pide el taller con la solicitud devuelta, o el evaluador)
 *  y RECHAZADA (el evaluador concluye «No cumple» al cerrar la lista de verificación).
 */
enum EstadoSolicitud: string
{
    case Pendiente = 'pendiente';
    case DevueltaTaller = 'devuelta_taller';
    case Subsanada = 'subsanada';
    case Evaluada = 'evaluada';
    case CorreccionTecnica = 'correccion_tecnica';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de evaluación',
            self::DevueltaTaller => 'Devuelta al taller',
            self::Subsanada => 'Corregida por el taller',
            self::Evaluada => 'En revisión final',
            self::CorreccionTecnica => 'Corrección técnica',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::DevueltaTaller => 'danger',
            self::Subsanada => 'info',
            self::Evaluada => 'warning',
            self::CorreccionTecnica => 'danger',
            self::Aprobada => 'success',
            self::Rechazada => 'danger',
            self::Cancelada => 'gray',
        };
    }

    /** Estados en los que el evaluador puede trabajar la solicitud. */
    public static function evaluables(): array
    {
        return [self::Pendiente->value, self::Subsanada->value, self::CorreccionTecnica->value];
    }

    /** Estados finales: la solicitud ya no se mueve. */
    public static function cerradas(): array
    {
        return [self::Aprobada->value, self::Rechazada->value, self::Cancelada->value];
    }

    /** Estados en los que el taller puede editar datos y adjuntos. */
    public static function editablesPorTaller(): array
    {
        return [self::Pendiente->value, self::DevueltaTaller->value];
    }

    public static function labelDe(?string $estado): string
    {
        return self::tryFrom((string) $estado)?->label() ?? ucfirst(str_replace('_', ' ', (string) $estado));
    }

    public static function colorDe(?string $estado): string
    {
        return self::tryFrom((string) $estado)?->color() ?? 'gray';
    }
}
