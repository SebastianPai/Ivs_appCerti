<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorResource;
use App\Models\Actividad;
use App\Models\EvaluacionGeolocalizacion;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ChipValidationService;
use App\Support\Archivo;
use App\Support\ChecklistTecnico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inspección sin conexión para el evaluador (/campo).
 *
 * 1. Con señal, el evaluador descarga sus solicitudes pendientes (datos()).
 * 2. En el taller, sin señal, diligencia declaración, GPS, chip, fotos y checklist en el celular
 *    (todo queda en el navegador: IndexedDB).
 * 3. Al volver la señal se sincroniza (sincronizar()). Queda todo guardado como "en progreso";
 *    el envío al revisor se hace en línea desde el checklist, con las mismas validaciones de siempre.
 */
class CampoController extends Controller
{
    private function evaluador(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user?->hasRole('evaluador'), 403, 'Solo los evaluadores usan la inspección sin conexión.');
        abort_if(SystemSetting::exige2fa($user) && ! $user->tiene2fa(), 403, 'Primero active la verificación en dos pasos desde el panel.');

        return $user;
    }

    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->hasRole('evaluador') && SystemSetting::exige2fa($user) && ! $user->tiene2fa()) {
            return redirect('/ivs'); // Filament lo lleva a configurar el 2FA
        }

        $this->evaluador($request);

        return response()
            ->view('campo.app', [
                'user' => $user,
                'config' => [
                    'usuario' => $user->id,
                    'datos' => route('campo.datos'),
                    'sincronizar' => url('/campo/sincronizar'),
                    'panel' => url('/ivs'),
                    'login' => url('/ivs/login'),
                ],
            ])
            ->header('Cache-Control', 'no-cache, private');
    }

    /** Solicitudes pendientes del evaluador + definición del checklist (para trabajar sin señal). */
    public function datos(Request $request): JsonResponse
    {
        $user = $this->evaluador($request);

        $solicitudes = EvaluadorResource::getEloquentQuery()
            ->whereIn('estado', EstadoSolicitud::evaluables())
            ->with(['user:id,name', 'vehicle.brand', 'vehicle.model', 'cilindros:id,solicitud_id,numero_serie', 'verificaciones' => fn ($q) => $q->where('evaluador_id', $user->id)->with('fotos', 'chip')])
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(function (Solicitud $s) {
                $v = $s->verificaciones->first();

                return [
                    'id' => $s->id,
                    'placa' => $s->placa(),
                    'taller' => $s->user?->name,
                    'vehiculo' => trim(($s->vehicle?->brand?->nombre ?? '').' '.($s->vehicle?->model?->nombre ?? '').' '.($s->vehicle?->year ?? '')),
                    'ciudad' => trim("{$s->owner_ciudad}, {$s->owner_departamento}", ', '),
                    'direccion' => $s->owner_direccion,
                    'estado' => EstadoSolicitud::labelDe($s->estado),
                    'observacion_revisor' => $s->estado === EstadoSolicitud::CorreccionTecnica->value ? $s->observaciones_revisor : null,
                    'cilindros' => $s->cilindros->pluck('numero_serie')->values(),
                    'guardado' => [
                        'declaracion' => (bool) $v?->conflicto_interes,
                        'chip' => $v?->chip?->codigo,
                        'observaciones' => $v?->observaciones,
                        'checklist' => $v?->datos_checklist ?? new \stdClass,
                        'fotos' => $v?->fotos->pluck('nombre_foto')->values() ?? [],
                    ],
                    'actualizada' => $s->updated_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'csrf' => csrf_token(),
            'generado' => now()->toIso8601String(),
            'chip_obligatorio' => SystemSetting::enabled('chip_required'),
            'fotos_obligatorias' => ViewSolicitud::FOTOS_OBLIGATORIAS,
            'ubicaciones' => ChecklistTecnico::UBICACIONES_CILINDRO,
            'secciones' => collect(ChecklistTecnico::secciones())->map(fn ($s) => [
                'titulo' => $s['titulo'],
                'descripcion' => $s['descripcion'],
                'items' => collect($s['items'])->map(fn ($i) => [
                    'id' => $i['id'],
                    'numeral' => $i['numeral'],
                    'texto' => $i['texto'],
                    'medicion' => (bool) ($i['medicion'] ?? false),
                    'minimo' => $i['minimo'] ?? null,
                ])->values(),
            ])->values(),
            'solicitudes' => $solicitudes,
        ])->header('Cache-Control', 'no-store');
    }

    /** Recibe lo diligenciado sin conexión para una solicitud. */
    public function sincronizar(Request $request, int $solicitud): JsonResponse
    {
        $user = $this->evaluador($request);

        /** @var Solicitud|null $registro */
        $registro = EvaluadorResource::getEloquentQuery()->with('cilindros')->find($solicitud);
        abort_unless($registro, 404, 'La solicitud ya no está asignada a usted.');

        if (! $registro->esEvaluable()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'La solicitud ya no está pendiente de evaluación (estado: '.EstadoSolicitud::labelDe($registro->estado).'). Lo diligenciado sin conexión no se aplicó.',
            ], 409);
        }

        $datos = $request->validate([
            'declaracion' => ['accepted'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0'],
            'capturado_en' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String(), 'after:'.now()->subDays(15)->toIso8601String()],
            'chip_codigo' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['nullable', 'json'],
            'fotos' => ['nullable', 'array', 'max:20'],
            'fotos.*.nombre' => ['required', 'string', 'max:120'],
            'fotos.*.archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
        ], [
            'declaracion.accepted' => 'Falta la declaración de no conflicto de interés.',
            'lat.required' => 'Falta la ubicación GPS.',
            'capturado_en.after' => 'La ubicación fue capturada hace más de 15 días. Vuelva a capturarla en el sitio.',
        ]);

        $avisos = [];

        $resultado = DB::transaction(function () use ($registro, $user, $datos, $request, &$avisos) {
            $capturado = Carbon::parse($datos['capturado_en']);
            $precision = isset($datos['precision']) ? round((float) $datos['precision'], 2) : null;

            EvaluacionGeolocalizacion::updateOrCreate(
                ['solicitud_id' => $registro->id, 'evaluador_id' => $user->id],
                [
                    'latitud' => $datos['lat'],
                    'longitud' => $datos['lng'],
                    'precision_m' => $precision,
                    'ip' => $request->ip(),
                    'user_agent' => mb_substr('[sin conexión] '.$request->userAgent(), 0, 1000),
                    'registrado_en' => $capturado,
                ]
            );

            $verificacion = SolicitudVerificacion::firstOrNew(['solicitud_id' => $registro->id, 'evaluador_id' => $user->id]);
            $verificacion->fill([
                'conflicto_interes' => true,
                'lat' => $datos['lat'],
                'lng' => $datos['lng'],
                'accuracy' => $precision,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'sincronizada_en' => now(),
            ]);

            if (array_key_exists('observaciones', $datos) && filled($datos['observaciones'])) {
                $verificacion->observaciones = $datos['observaciones'];
            }

            // Chip: si no es válido se avisa, pero no se pierde lo demás
            $chipOk = false;
            try {
                $chip = ChipValidationService::validar($datos['chip_codigo'] ?? null, $registro, 'chip_codigo');
                if ($chip) {
                    $verificacion->id_chip = $chip->id;
                }
                $chipOk = $chip !== null || ! SystemSetting::enabled('chip_required');
            } catch (ValidationException $e) {
                $avisos[] = collect($e->errors())->flatten()->first();
            }

            $checklist = $this->limpiarChecklist(json_decode($datos['checklist'] ?? '{}', true) ?: [], $registro);
            if ($checklist !== []) {
                $verificacion->datos_checklist = [...($verificacion->datos_checklist ?? []), ...$checklist];
            }

            if (! $verificacion->exists || in_array($verificacion->estado, ['iniciada', 'devuelta'], true)) {
                $verificacion->estado = 'validada';
            }

            $verificacion->save();

            // Fotos: la nueva reemplaza a la que tenga el mismo nombre
            $fotosNuevas = 0;
            foreach ($request->file('fotos', []) as $i => $foto) {
                $nombre = trim((string) ($datos['fotos'][$i]['nombre'] ?? ''));
                if ($nombre === '' || ! isset($foto['archivo'])) {
                    continue;
                }

                $ruta = $foto['archivo']->store("evaluadores/{$registro->id}/fotos", Archivo::DISCO);
                $verificacion->fotos()->where('nombre_foto', $nombre)->delete();
                $verificacion->fotos()->create(['nombre_foto' => $nombre, 'ruta_foto' => $ruta]);
                $fotosNuevas++;
            }

            $tiene = $verificacion->fotos()->pluck('nombre_foto');
            $faltan = collect(ViewSolicitud::FOTOS_OBLIGATORIAS)->diff($tiene);

            if ($faltan->isNotEmpty()) {
                $avisos[] = 'Faltan fotos obligatorias: '.$faltan->implode(', ').'.';
            }

            // Paso 2 completo: el evaluador puede ir directo al checklist en línea
            if ($faltan->isEmpty() && $chipOk) {
                $verificacion->update(['estado' => 'en_progreso']);
            }

            Actividad::registrar(
                'sincronizacion',
                "Inspección sincronizada desde el modo sin conexión (GPS capturado el {$capturado->format('d/m/Y h:i a')}, {$fotosNuevas} foto(s), ".count($checklist).' respuesta(s) del checklist)',
                $verificacion,
                $registro->id,
            );

            return $verificacion->fresh();
        });

        $listo = $resultado->estado === 'en_progreso';

        return response()->json([
            'ok' => true,
            'listo_para_enviar' => $listo,
            'avisos' => array_values(array_filter($avisos)),
            'mensaje' => $listo
                ? 'Inspección guardada. Revise el checklist en línea y envíela al revisor.'
                : 'Inspección guardada parcialmente. Complete lo que falta en línea.',
            'siguiente' => $listo
                ? EvaluadorResource::getUrl('checklist', ['record' => $registro])
                : EvaluadorResource::getUrl('view', ['record' => $registro]),
        ]);
    }

    /** Solo se aceptan claves y valores del checklist oficial. */
    private function limpiarChecklist(array $entrada, Solicitud $solicitud): array
    {
        $limpio = [];

        foreach (ChecklistTecnico::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                $id = $item['id'];

                if (in_array($entrada[$id] ?? null, ['c', 'nc', 'na'], true)) {
                    $limpio[$id] = $entrada[$id];
                }

                if (($item['medicion'] ?? false) && is_numeric($entrada["{$id}_valor"] ?? null) && $entrada["{$id}_valor"] >= 0) {
                    $limpio["{$id}_valor"] = (float) $entrada["{$id}_valor"];
                }
            }
        }

        $series = $solicitud->cilindros->pluck('numero_serie');
        $cilindros = collect($entrada['verificacion_cilindros'] ?? [])
            ->filter(fn ($c) => is_array($c) && $series->contains($c['numero_serie'] ?? null))
            ->map(fn ($c) => [
                'numero_serie' => $c['numero_serie'],
                'ultra_liviano' => in_array($c['ultra_liviano'] ?? null, ['si', 'no'], true) ? $c['ultra_liviano'] : null,
                'ubicacion' => array_values(array_intersect((array) ($c['ubicacion'] ?? []), array_keys(ChecklistTecnico::UBICACIONES_CILINDRO))),
            ])
            ->values()
            ->all();

        if ($cilindros !== []) {
            $limpio['verificacion_cilindros'] = $cilindros;
        }

        return $limpio;
    }
}
