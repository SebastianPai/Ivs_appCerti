<?php

namespace App\Support;

use App\Enums\EstadoSolicitud;
use App\Models\Actividad;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicadores del tablero (tiempos, productividad, devoluciones). Se calculan en PHP para
 * funcionar igual en MySQL y en SQLite (pruebas); el volumen de un periodo es pequeño.
 */
class Indicadores
{
    public readonly Carbon $desde;

    public readonly Carbon $hasta;

    public function __construct(?string $desde = null, ?string $hasta = null)
    {
        $this->desde = $desde ? Carbon::parse($desde)->startOfDay() : now()->subMonthsNoOverflow(5)->startOfMonth();
        $this->hasta = $hasta ? Carbon::parse($hasta)->endOfDay() : now()->endOfDay();
    }

    public static function desdeFiltros(?array $filtros): self
    {
        return new self($filtros['desde'] ?? null, $filtros['hasta'] ?? null);
    }

    /** Horas entre dos fechas (null si falta alguna). */
    private static function horas($inicio, $fin): ?float
    {
        return $inicio && $fin ? max(0, Carbon::parse($inicio)->diffInMinutes(Carbon::parse($fin)) / 60) : null;
    }

    /** "5 h" o "2,4 días". */
    public static function duracion(?float $horas): string
    {
        if ($horas === null) {
            return '—';
        }

        return $horas < 48
            ? number_format($horas, $horas < 10 ? 1 : 0, ',', '.').' h'
            : number_format($horas / 24, 1, ',', '.').' días';
    }

    private static function promedio(Collection $valores): ?float
    {
        $valores = $valores->filter(fn ($v) => $v !== null);

        return $valores->isEmpty() ? null : $valores->avg();
    }

    // ------------------------------------------------------------------

    /** Solicitudes aprobadas en el periodo, con su verificación. */
    private function aprobadas(): Collection
    {
        return once(fn () => Solicitud::query()
            ->where('estado', EstadoSolicitud::Aprobada->value)
            ->whereBetween('fecha_aprobacion', [$this->desde, $this->hasta])
            ->with('verificacion')
            ->get());
    }

    private function creadas(): Collection
    {
        return once(fn () => Solicitud::query()
            ->whereBetween('created_at', [$this->desde, $this->hasta])
            ->get(['id', 'user_id', 'estado', 'observacion_devolucion', 'owner_departamento', 'created_at']));
    }

    /** Cambios de estado del periodo: [solicitud_id, user_id, a, created_at]. */
    private function cambiosDeEstado(): Collection
    {
        return once(fn () => Actividad::query()
            ->where('evento', 'estado')
            ->whereBetween('created_at', [$this->desde, $this->hasta])
            ->get(['solicitud_id', 'user_id', 'cambios', 'created_at'])
            ->map(fn (Actividad $a) => (object) [
                'solicitud_id' => $a->solicitud_id,
                'user_id' => $a->user_id,
                'a' => $a->cambios['estado']['despues'] ?? null,
                'created_at' => $a->created_at,
            ]));
    }

    /** IDs de solicitudes devueltas al taller al menos una vez. */
    private function devueltasAlTaller(): Collection
    {
        $porHistorial = Actividad::query()
            ->where('evento', 'estado')
            ->where('cambios', 'like', '%'.EstadoSolicitud::DevueltaTaller->value.'%')
            ->pluck('solicitud_id');

        return $this->creadas()
            ->filter(fn ($s) => filled($s->observacion_devolucion) || $porHistorial->contains($s->id))
            ->pluck('id');
    }

    // ------------------------------------------------------------------

    public function tiempos(): array
    {
        $aprobadas = $this->aprobadas();
        $creadas = $this->creadas();

        $enviadas = SolicitudVerificacion::query()
            ->whereBetween('verificada_en', [$this->desde, $this->hasta])
            ->with('solicitud:id,created_at')
            ->get();

        return [
            'ciclo' => self::promedio($aprobadas->map(fn ($s) => self::horas($s->created_at, $s->fecha_aprobacion))),
            'evaluacion' => self::promedio($enviadas->map(fn ($v) => self::horas($v->solicitud?->created_at, $v->verificada_en))),
            'revision' => self::promedio($aprobadas->map(fn ($s) => self::horas($s->verificacion?->verificada_en, $s->fecha_aprobacion))),
            'creadas' => $creadas->count(),
            'aprobadas' => $aprobadas->count(),
            'tasa_devolucion' => $creadas->isEmpty() ? null : round($this->devueltasAlTaller()->count() * 100 / $creadas->count()),
            'estancadas' => Solicitud::query()
                ->where('estado', '!=', EstadoSolicitud::Aprobada->value)
                ->where('updated_at', '<', now()->subDays(5))
                ->count(),
        ];
    }

    /** Creadas y aprobadas por mes: ['meses' => [...], 'creadas' => [...], 'aprobadas' => [...]] */
    public function porMes(): array
    {
        $meses = collect();
        for ($m = $this->desde->copy()->startOfMonth(); $m <= $this->hasta; $m->addMonthNoOverflow()) {
            $meses->push($m->format('Y-m'));
        }

        $creadas = $this->creadas()->countBy(fn ($s) => $s->created_at->format('Y-m'));
        $aprobadas = $this->aprobadas()->countBy(fn ($s) => $s->fecha_aprobacion->format('Y-m'));

        return [
            'meses' => $meses->map(fn ($m) => ucfirst(Carbon::createFromFormat('Y-m-d', "{$m}-01")->translatedFormat('M Y')))->all(),
            'creadas' => $meses->map(fn ($m) => $creadas[$m] ?? 0)->all(),
            'aprobadas' => $meses->map(fn ($m) => $aprobadas[$m] ?? 0)->all(),
        ];
    }

    /** Los 10 departamentos con más solicitudes en el periodo. */
    public function porDepartamento(): Collection
    {
        return $this->creadas()
            ->countBy(fn ($s) => filled($s->owner_departamento) ? $s->owner_departamento : 'Sin dato')
            ->sortDesc()
            ->take(10);
    }

    /** Una fila por evaluador con su desempeño en el periodo. */
    public function evaluadores(): Collection
    {
        $enviadas = SolicitudVerificacion::query()
            ->whereBetween('verificada_en', [$this->desde, $this->hasta])
            ->with('solicitud:id,created_at')
            ->get()
            ->groupBy('evaluador_id');

        $aprobadas = $this->aprobadas()->groupBy(fn ($s) => $s->verificacion?->evaluador_id);
        $cambios = $this->cambiosDeEstado();
        $evaluadorDe = SolicitudVerificacion::query()
            ->whereIn('solicitud_id', $cambios->pluck('solicitud_id')->filter()->unique())
            ->pluck('evaluador_id', 'solicitud_id');

        return User::role('evaluador')->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => [
                'nombre' => $u->name,
                'enviadas' => ($enviadas[$u->id] ?? collect())->count(),
                'aprobadas' => ($aprobadas[$u->id] ?? collect())->count(),
                'devueltas_taller' => $cambios->where('user_id', $u->id)->where('a', EstadoSolicitud::DevueltaTaller->value)->count(),
                'correcciones' => $cambios->where('a', EstadoSolicitud::CorreccionTecnica->value)
                    ->filter(fn ($c) => ($evaluadorDe[$c->solicitud_id] ?? null) === $u->id)
                    ->count(),
                'tiempo' => self::duracion(self::promedio(($enviadas[$u->id] ?? collect())
                    ->map(fn ($v) => self::horas($v->solicitud?->created_at, $v->verificada_en)))),
            ])
            ->sortByDesc('enviadas')
            ->values();
    }

    /** Una fila por revisor. */
    public function revisores(): Collection
    {
        $aprobadas = $this->aprobadas()->groupBy('revisor_id');
        $cambios = $this->cambiosDeEstado();

        return User::role('revisor')->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => [
                'nombre' => $u->name,
                'aprobadas' => ($aprobadas[$u->id] ?? collect())->count(),
                'devueltas' => $cambios->where('user_id', $u->id)->where('a', EstadoSolicitud::CorreccionTecnica->value)->count(),
                'tiempo' => self::duracion(self::promedio(($aprobadas[$u->id] ?? collect())
                    ->map(fn ($s) => self::horas($s->verificacion?->verificada_en, $s->fecha_aprobacion)))),
            ])
            ->sortByDesc('aprobadas')
            ->values();
    }

    /** Talleres con su tasa de devolución (los 10 con más solicitudes en el periodo). */
    public function talleres(): Collection
    {
        $creadas = $this->creadas()->groupBy('user_id');
        $devueltas = $this->devueltasAlTaller();
        $aprobadas = $this->aprobadas()->groupBy('user_id');
        $dias = \App\Models\SystemSetting::vigencia()['dias_aviso'];
        $porRenovar = Solicitud::query()->porRenovar($dias)->get(['id', 'user_id'])->countBy('user_id');

        return User::whereIn('id', $creadas->keys())->get(['id', 'name'])
            ->map(function (User $u) use ($creadas, $devueltas, $aprobadas, $porRenovar) {
                $suyas = $creadas[$u->id];
                $conDevolucion = $suyas->pluck('id')->intersect($devueltas)->count();

                return [
                    'nombre' => $u->name,
                    'creadas' => $suyas->count(),
                    'aprobadas' => ($aprobadas[$u->id] ?? collect())->count(),
                    'devueltas' => $conDevolucion,
                    'tasa' => round($conDevolucion * 100 / max(1, $suyas->count())),
                    'por_renovar' => $porRenovar[$u->id] ?? 0,
                ];
            })
            ->sortByDesc('creadas')
            ->take(10)
            ->values();
    }
}
