<?php

namespace App\Exports;

use App\Enums\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exportables con formato fijo (los de la plataforma anterior): una hoja "data",
 * la fila 1 con los encabezados exactos y todas las celdas como texto.
 *
 * Diferencias a propósito con la plataforma anterior:
 *  - la fecha final siempre se incluye (allá el del revisor la excluía);
 *  - sin filas no se genera archivo (allá Operaciones descargaba un Excel vacío);
 *  - los dos exportables SICOM tienen nombres de archivo distintos.
 */
class Exportables
{
    public const TIPOS = [
        'cliente' => [
            'archivo' => 'ExportadoCliente',
            'etiqueta' => 'Exportables de Mis Solicitudes',
            'ayuda' => 'Una fila por solicitud del taller. Filtra por fecha de inspección o de creación.',
            'roles' => ['admin', 'cliente'],
        ],
        'revisor' => [
            'archivo' => 'Exportadorevisor',
            'etiqueta' => 'Exportables del revisor',
            'ayuda' => 'Una fila por solicitud inspeccionada. Filtra por fecha de inspección.',
            'roles' => ['admin', 'revisor'],
        ],
        'sicom' => [
            'archivo' => 'ExportadoSicom',
            'etiqueta' => 'Exportables SICOM (por cilindro)',
            'ayuda' => 'Una fila por cilindro de cada vehículo certificado. Filtra por fecha de certificación.',
            'roles' => ['admin'],
        ],
        'externo' => [
            'archivo' => 'ExportadoSicom',
            'etiqueta' => 'Mis Exportados (SICOM por certificado)',
            'ayuda' => 'Una fila por certificado emitido. Filtra por fecha de certificación.',
            'roles' => ['admin', 'externo'],
        ],
        'ivs' => [
            'archivo' => 'ExportadoIvs',
            'etiqueta' => 'Exportables IVS',
            'ayuda' => 'Certificados emitidos, por taller o por evaluador.',
            'roles' => ['admin'],
        ],
        'operaciones' => [
            'archivo' => 'Operaciones Realizadas',
            'etiqueta' => 'Reporte de Operaciones',
            'ayuda' => 'Inspecciones enviadas por el evaluador. Filtra por fecha de inspección.',
            'roles' => ['admin', 'evaluador'],
        ],
    ];

    public const ENCABEZADOS = [
        'cliente' => [
            'id', 'estado', 'tipo', 'identificacion', 'certificate', 'tipo_servicio', 'nombre_cliente', 'telefono_cliente',
            'numbCer', 'fecha_certificacion',
        ],
        'revisor' => [
            'numero_solicitud', 'placa_chasis', 'taller', 'ciudad', 'evaluador', 'fecha_inspeccion',
            'fecha_proxima_inspeccion', 'numero_certificado', 'servicio', 'estado_solicitud', 'fecha_revisor',
        ],
        'sicom' => [
            'PLACA', 'VIN', 'FECHA_INSTALACION', 'CHIP_IDROM', 'FECHA_ULTIMA_REVISION_ANUAL', 'NUMERO_SERIAL_CILINDRO',
            'MARCA_CILINDRO', 'CAPACIDAD_CILINDRO', 'FECHA_PROXIMA_PH_CILINDRO', 'FECHA_FABRICACION_CILINDRO',
            'NUMERO_SERIAL_REGULADOR', 'MARCA_REGULADOR',
        ],
        'externo' => [
            'id_tipo_certificado', 'tipo_certificacion', 'placa', 'vin', 'idrom', 'taller', 'nit_taller_ultima_certificacion',
            'ciudad', 'fecha_instalacion', 'fecha_ultima_revision_periodica', 'fecha_proxima_revision_periodica',
            'evaluador', 'nit_certificador', 'numero_certificado',
        ],
        'ivs' => [
            'placa', 'tipo_servicio', 'usuario', 'fecha_expedicion', 'fecha_creacion', 'fecha_expedicion_sin_hora',
            'fecha_creacion_sin_hora', 'numero_certificado',
        ],
        'operaciones' => [
            'id', 'taller', 'servicio', 'placa_chasis', 'numero_certificado', 'fecha_expedicion', 'evaluador', 'estado',
        ],
    ];

    private const FECHA_HORA = 'Y-m-d H:i:s';

    /** NIT del organismo certificador mientras no se cambie en Configuración. */
    public const NIT_CERTIFICADOR = '900524342';

    /** La próxima prueba hidrostática es la última + 5 años. */
    private const ANIOS_PRUEBA_HIDROSTATICA = 5;

    /** SICOM no admite instalaciones anteriores a este año en el archivo por certificado. */
    private const ANIO_MINIMO_INSTALACION = 2007;

    /** @return array<string, string> tipo => etiqueta, solo los que el usuario puede descargar */
    public static function tiposPara(?User $user): array
    {
        return collect(self::TIPOS)
            ->filter(fn (array $tipo) => (bool) $user?->hasAnyRole($tipo['roles']))
            ->map(fn (array $tipo) => $tipo['etiqueta'])
            ->all();
    }

    public static function puede(?User $user, string $tipo): bool
    {
        return isset(self::TIPOS[$tipo]) && (bool) $user?->hasAnyRole(self::TIPOS[$tipo]['roles']);
    }

    public static function nombreArchivo(string $tipo): string
    {
        return self::TIPOS[$tipo]['archivo'].'_export_'.now()->getTimestampMs().'.xlsx';
    }

    /**
     * Filas del exportable (sin encabezado).
     *
     * Filtros: desde, hasta (Y-m-d, ambos incluidos), placa, service_type_id, estado, revisada (si|no|devuelta),
     * fecha_de (inspeccion|creacion), certificada (si|no),
     * tipo_fecha (creacion|certificacion), modo (taller|inspector), usuario_id.
     *
     * @return array<int, array<int, string>>
     */
    public static function filas(string $tipo, array $filtros, User $user): array
    {
        abort_unless(self::puede($user, $tipo), 403);

        $filas = match ($tipo) {
            'cliente' => self::cliente($filtros, $user),
            'revisor' => self::revisor($filtros, $user),
            'sicom' => self::sicom($filtros),
            'externo' => self::externo($filtros, $user),
            'ivs' => self::ivs($filtros),
            'operaciones' => self::operaciones($filtros, $user),
        };

        // Todo viaja como texto y lo que no existe queda como celda vacía
        return array_map(fn (array $fila) => array_map(fn ($valor) => (string) ($valor ?? ''), $fila), $filas);
    }

    /**
     * Escribe el .xlsx y devuelve cuántas filas de datos quedaron. Con $columnas (nombres del
     * encabezado) salen solo esas, en el orden del formato; sin él, todas.
     */
    public static function escribir(string $tipo, array $filas, string $ruta, ?array $columnas = null): int
    {
        $visibles = array_keys($columnas === null
            ? self::ENCABEZADOS[$tipo]
            : array_intersect(self::ENCABEZADOS[$tipo], $columnas));
        $recortar = fn (array $fila) => array_map(fn (int $i) => $fila[$i], $visibles);

        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->getCurrentSheet()->setName('data');
        $writer->addRow(Row::fromValues($recortar(self::ENCABEZADOS[$tipo])));

        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues($recortar($fila)));
        }

        $writer->close();

        return count($filas);
    }

    public static function descargar(string $tipo, array $filas, ?array $columnas = null)
    {
        $archivo = self::nombreArchivo($tipo);
        $ruta = storage_path('app/private/'.$archivo);

        self::escribir($tipo, $filas, $ruta, $columnas);

        return response()->download($ruta, $archivo)->deleteFileAfterSend();
    }

    // -------- Exportables --------

    /** Mis Solicitudes del taller. El taller solo ve las suyas; el admin, las de todos. De la más nueva a la más vieja. */
    private static function cliente(array $f, User $user): array
    {
        $base = ($f['fecha_de'] ?? 'inspeccion') === 'creacion'
            ? self::rango(self::comunes(Solicitud::query(), $f), 'created_at', $f)
            : self::inspeccionadas($f);

        return $base
            ->when(! $user->hasRole('admin'), fn (Builder $q) => $q->where('user_id', $user->id))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(($f['certificada'] ?? null) === 'si', fn (Builder $q) => $q->whereNotNull('codigo'))
            ->when(($f['certificada'] ?? null) === 'no', fn (Builder $q) => $q->whereNull('codigo'))
            ->with(['serviceType', 'vehicleIdentificationType', 'vehicle'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Solicitud $s) => [
                $s->id,
                EstadoSolicitud::labelDe($s->estado),
                mb_strtoupper((string) $s->vehicleIdentificationType?->nombre),
                $s->placa(),
                filled($s->codigo) ? 'Certificado' : 'No certificado',
                $s->serviceType?->nombre,
                trim($s->owner_nombre.' '.$s->owner_apellido),
                $s->owner_telefono,
                $s->codigo,
                filled($s->codigo) ? $s->fecha_aprobacion?->format(self::FECHA_HORA) : null,
            ])
            ->all();
    }

    private static function revisor(array $f, User $user): array
    {
        return self::inspeccionadas($f)
            ->when(! $user->hasRole('admin'), fn (Builder $q) => $q->visiblesParaRevisor($user))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(($f['revisada'] ?? null) === 'si', fn (Builder $q) => $q->whereNotNull('revisada_en'))
            ->when(($f['revisada'] ?? null) === 'no', fn (Builder $q) => $q->pendientesDeRevision())
            ->when(($f['revisada'] ?? null) === 'devuelta', fn (Builder $q) => $q->where('estado', EstadoSolicitud::CorreccionTecnica->value))
            ->with(['user', 'serviceType', 'verificacion.evaluador'])
            ->get()
            ->sortBy(fn (Solicitud $s) => [$s->verificacion?->verificada_en?->getTimestamp(), $s->id])
            ->map(fn (Solicitud $s) => [
                $s->id,
                $s->placa(),
                $s->user?->name,
                $s->user?->city,
                $s->verificacion?->evaluador?->name,
                $s->verificacion?->verificada_en?->format(self::FECHA_HORA),
                self::proxima($s)?->format(self::FECHA_HORA),
                $s->codigo,
                $s->serviceType?->nombre,
                EstadoSolicitud::labelDe($s->estado),
                $s->revisada_en?->format(self::FECHA_HORA),
            ])
            ->values()
            ->all();
    }

    /** Formato de carga SICOM: una fila por cilindro; el vehículo se repite. Fechas DD/MM/AAAA. */
    private static function sicom(array $f): array
    {
        $solicitudes = self::certificadas($f)
            ->with(['vehicle', 'vehicleIdentificationType', 'serviceType', 'verificacion.chip', 'cilindros.brand', 'regulators.brand'])
            ->get();
        $reales = self::instalacionesReales($solicitudes);
        $fecha = fn ($valor) => $valor ? Carbon::parse($valor)->format('d/m/Y') : null;

        $filas = [];
        foreach ($solicitudes as $s) {
            $regulador = $s->regulators->first();
            // En un servicio inicial la instalación es el servicio mismo; si no, la fecha real o el 1 de enero del año modelo
            $instalacion = $s->serviceType?->esInicial()
                ? $s->fecha_aprobacion
                : ($reales[$s->id] ?? self::delModelo($s, 1));
            $vehiculo = [
                ...self::placaYVin($s),
                $fecha($instalacion),
                $s->verificacion?->chip?->codigo,
                $fecha($s->fecha_aprobacion),
            ];
            $equipoRegulador = [$regulador?->numero_serie, $regulador?->brand?->codigo_sicom];

            // Sin cilindros registrados el vehículo sale igual, con las columnas del cilindro vacías
            foreach ($s->cilindros->isEmpty() ? [null] : $s->cilindros as $c) {
                $filas[] = [
                    ...$vehiculo,
                    $c?->numero_serie,
                    $c?->brand?->codigo_sicom,
                    $c?->capacidad,
                    $fecha($c?->fecha_prueba ? Carbon::parse($c->fecha_prueba)->addYears(self::ANIOS_PRUEBA_HIDROSTATICA) : null),
                    $fecha($c?->fecha_fabricacion),
                    ...$equipoRegulador,
                ];
            }
        }

        return $filas;
    }

    /** Una fila por certificado emitido. Fechas AAAA-MM-DD. */
    private static function externo(array $f, User $user): array
    {
        // El cambio de chip no es una certificación: no entra en este archivo
        $solicitudes = self::certificadas($f)
            // Como en la plataforma anterior, el Externo Exportado solo ve los talleres que tiene asignados
            ->when(! $user->hasRole('admin'), fn (Builder $q) => $q->whereIn('user_id', $user->talleresExportables()->select('users.id')))
            ->whereDoesntHave('serviceType', fn ($t) => $t->where('clase', 'cambio_chip'))
            ->with(['user', 'vehicle', 'vehicleIdentificationType', 'serviceType', 'verificacion.chip', 'verificacion.evaluador'])
            ->get();
        $reales = self::instalacionesReales($solicitudes);
        $nitCertificador = self::nitCertificador();

        return $solicitudes->map(fn (Solicitud $s) => [
            $s->serviceType?->codigoExportado(),
            $s->serviceType?->nombreExportado(),
            ...self::placaYVin($s),
            $s->verificacion?->chip?->codigo,
            $s->user?->name,
            $s->user?->nit,
            $s->user?->city,
            // Aquí nunca se usa la fecha del servicio: la real o el 1 de febrero del año modelo (mínimo 2007)
            ($reales[$s->id] ?? self::delModelo($s, 2, self::ANIO_MINIMO_INSTALACION))?->format('Y-m-d'),
            $s->fecha_aprobacion?->format('Y-m-d'),
            self::proxima($s)?->format('Y-m-d'),
            $s->verificacion?->evaluador?->name,
            $nitCertificador,
            $s->codigo,
        ])->all();
    }

    private static function ivs(array $f): array
    {
        $porInspector = ($f['modo'] ?? 'taller') === 'inspector';
        $columna = ($f['tipo_fecha'] ?? 'certificacion') === 'creacion' ? 'created_at' : 'fecha_aprobacion';
        $usuario = $f['usuario_id'] ?? null;

        return self::rango(Solicitud::query()->where('estado', EstadoSolicitud::Aprobada->value), $columna, $f)
            ->when($usuario && ! $porInspector, fn (Builder $q) => $q->where('user_id', $usuario))
            ->when($usuario && $porInspector, fn (Builder $q) => $q->whereHas('verificaciones', fn ($v) => $v->where('evaluador_id', $usuario)))
            ->with(['user', 'serviceType', 'verificacion.evaluador'])
            ->orderBy($columna)->orderBy('id')
            ->get()
            ->map(fn (Solicitud $s) => [
                $s->placa(),
                $s->serviceType?->nombre,
                $porInspector ? $s->verificacion?->evaluador?->name : $s->user?->name,
                $s->fecha_aprobacion?->format(self::FECHA_HORA),
                $s->created_at?->format(self::FECHA_HORA),
                $s->fecha_aprobacion?->format('Y-m-d'),
                $s->created_at?->format('Y-m-d'),
                $s->codigo,
            ])
            ->all();
    }

    private static function operaciones(array $f, User $user): array
    {
        // El evaluador solo ve lo suyo; el admin, lo de todos
        $evaluador = $user->hasRole('admin') ? null : $user->id;

        return self::inspeccionadas($f, $evaluador)
            ->with(['user', 'serviceType', 'verificacion.evaluador'])
            ->get()
            ->sortBy(fn (Solicitud $s) => [$s->verificacion?->verificada_en?->getTimestamp(), $s->id])
            ->map(fn (Solicitud $s) => [
                $s->id,
                mb_strtoupper((string) $s->user?->name),
                $s->serviceType?->nombre,
                $s->placa(),
                $s->codigo,
                // Expedición = envío de la lista; taller y evaluador van en mayúsculas, como en el archivo original
                ($s->fecha_aprobacion ?? $s->verificacion?->verificada_en)?->format(self::FECHA_HORA),
                mb_strtoupper((string) $s->verificacion?->evaluador?->name),
                EstadoSolicitud::labelDe($s->estado),
            ])
            ->values()
            ->all();
    }

    // -------- Consultas compartidas --------

    /** Solicitudes cuya inspección se envió dentro del rango. */
    private static function inspeccionadas(array $f, ?int $evaluadorId = null): Builder
    {
        return self::comunes(Solicitud::query(), $f)
            ->whereHas('verificaciones', function ($v) use ($f, $evaluadorId) {
                self::rango($v->whereNotNull('verificada_en'), 'verificada_en', $f)
                    ->when($evaluadorId, fn ($q) => $q->where('evaluador_id', $evaluadorId));
            });
    }

    /** Certificados emitidos dentro del rango, en el orden en que se emitieron. */
    private static function certificadas(array $f): Builder
    {
        return self::rango(Solicitud::query()->where('estado', EstadoSolicitud::Aprobada->value), 'fecha_aprobacion', $f)
            ->orderBy('fecha_aprobacion')
            ->orderBy('id');
    }

    private static function comunes(Builder $q, array $f): Builder
    {
        return $q
            ->when(filled($f['placa'] ?? null), fn (Builder $q) => $q->where('vehicle_identification', 'like', '%'.trim($f['placa']).'%'))
            ->when(filled($f['service_type_id'] ?? null), fn (Builder $q) => $q->where('service_type_id', $f['service_type_id']));
    }

    /** Rango por día: la fecha final se incluye completa. */
    private static function rango($q, string $columna, array $f)
    {
        return $q
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->whereDate($columna, '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->whereDate($columna, '<=', $f['hasta']));
    }

    private static function proxima(Solicitud $s): ?Carbon
    {
        // Sin número no hay certificado que renovar: el cambio de chip la deja vacía.
        // Va sin hora (00:00:00), como en los archivos de la plataforma anterior.
        if (blank($s->codigo)) {
            return null;
        }

        return ($s->vence_el ?? $s->fecha_aprobacion?->copy()->addYear())?->copy()->startOfDay();
    }

    /** Identificado por placa: VIN vacío. Identificado por chasis: placa vacía y el chasis como VIN. */
    private static function placaYVin(Solicitud $s): array
    {
        $porChasis = str_contains(mb_strtolower((string) $s->vehicleIdentificationType?->nombre), 'chasis');

        return $porChasis ? [null, $s->placa()] : [$s->placa(), null];
    }

    /**
     * Fecha de instalación que digitó el taller, por solicitud. Si esta solicitud no la trae,
     * vale la más reciente registrada para el mismo vehículo.
     *
     * @return array<int, Carbon|null>
     */
    private static function instalacionesReales($solicitudes): array
    {
        $deOtras = Solicitud::query()
            ->whereNotNull('fecha_instalacion')
            ->whereIn('vehicle_identification', $solicitudes->whereNull('fecha_instalacion')->pluck('vehicle_identification')->filter()->unique())
            ->orderBy('id')
            ->pluck('fecha_instalacion', 'vehicle_identification');

        return $solicitudes->mapWithKeys(fn (Solicitud $s) => [
            $s->id => $s->fecha_instalacion ?? $deOtras[$s->vehicle_identification] ?? null,
        ])->all();
    }

    /** Día 1 del mes indicado del año modelo del vehículo (respaldo cuando no hay fecha real). */
    private static function delModelo(Solicitud $s, int $mes, int $anioMinimo = 0): ?Carbon
    {
        $anio = (int) $s->vehicle?->year;

        return $anio ? Carbon::create(max($anio, $anioMinimo), $mes, 1) : null;
    }

    public static function nitCertificador(): string
    {
        return SystemSetting::meta('organismo', 'nit') ?: self::NIT_CERTIFICADOR;
    }
}
