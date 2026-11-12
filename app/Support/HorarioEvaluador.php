<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * «Restringir acceso inspector» de la plataforma anterior: fuera del horario, el evaluador no
 * abre solicitudes desde Solicitudes Abiertas. El admin no tiene la restricción.
 */
class HorarioEvaluador
{
    public const MENSAJE = 'Se encuentra fuera de su horario de prestación de servicios, por favor inténtelo dentro de los horarios definidos. Gracias';

    /** Días ISO: 1 lunes … 7 domingo. */
    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** @return array{horario_desde: string, horario_hasta: string, horario_dias: list<int>} */
    public static function horario(): array
    {
        return [
            'horario_desde' => SystemSetting::meta('restriccion_horario_inspector', 'desde') ?? '07:00',
            'horario_hasta' => SystemSetting::meta('restriccion_horario_inspector', 'hasta') ?? '18:00',
            'horario_dias' => SystemSetting::meta('restriccion_horario_inspector', 'dias') ?? [1, 2, 3, 4, 5, 6],
        ];
    }

    public static function fueraDeHorario(?User $user, ?Carbon $ahora = null): bool
    {
        if (! $user || $user->hasRole('admin') || ! $user->hasRole('evaluador') || ! SystemSetting::enabled('restriccion_horario_inspector')) {
            return false;
        }

        $ahora ??= now();
        $h = self::horario();

        return ! in_array($ahora->isoWeekday(), array_map('intval', $h['horario_dias']), true)
            || $ahora->format('H:i') < $h['horario_desde']
            || $ahora->format('H:i') >= $h['horario_hasta'];
    }
}
