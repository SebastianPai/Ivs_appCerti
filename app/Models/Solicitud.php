<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\SolicitudRegulador;
use App\Models\SolicitudCilindro;
use App\Models\SolicitudVerificacion;
use App\Models\ServiceType;
use App\Models\VehicleIdentificationType;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Enums\EstadoSolicitud;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $service_type_id
 * @property int|null $vehicle_id
 * @property int|null $vehicle_identification_type_id
 * @property string|null $vehicle_identification
 * @property string|null $owner_document_type
 * @property string|null $owner_document
 * @property string|null $owner_nombre
 * @property string|null $owner_apellido
 * @property string|null $owner_direccion
 * @property string|null $owner_departamento
 * @property string|null $owner_ciudad
 * @property string|null $owner_telefono
 * @property string|null $owner_email
 * @property int|null $combustion_system_id
 * @property int|null $application_type_id
 * @property int|null $technology_id
 * @property string $estado
 * @property string|null $observacion_devolucion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SolicitudAdjunto> $adjuntos
 * @property-read int|null $adjuntos_count
 * @property-read \App\Models\ApplicationType|null $applicationType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SolicitudCilindro> $cilindros
 * @property-read int|null $cilindros_count
 * @property-read \App\Models\CombustionSystem|null $combustionSystem
 * @property-read User|null $evaluador
 * @property-read \App\Models\EvaluacionGeolocalizacion|null $geolocalizacion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SolicitudRegulador> $regulators
 * @property-read int|null $regulators_count
 * @property-read ServiceType|null $serviceType
 * @property-read \App\Models\Technology|null $technology
 * @property-read User $user
 * @property-read \App\Models\Vehicle|null $vehicle
 * @property-read VehicleBrand|null $vehicleBrand
 * @property-read VehicleIdentificationType|null $vehicleIdentificationType
 * @property-read VehicleModel|null $vehicleModel
 * @property-read VehicleType|null $vehicleType
 * @property-read SolicitudVerificacion|null $verificacion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SolicitudVerificacion> $verificaciones
 * @property-read int|null $verificaciones_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereApplicationTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereCombustionSystemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereObservacionDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerApellido($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerCiudad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerDepartamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerDireccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereOwnerTelefono($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereServiceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereTechnologyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereVehicleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereVehicleIdentification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Solicitud whereVehicleIdentificationTypeId($value)
 * @mixin \Eloquent
 */
class Solicitud extends Model
{
    use Auditable, HasFactory;

    protected $table = 'solicituds';

    /** El aviso de vencimiento se registra como evento propio ("aviso"), no como modificación. */
    protected array $noAuditar = ['aviso_vencimiento_at'];

    protected $fillable = [
        'user_id',
        'service_type_id',
        'vehicle_id',

        // Identificación del vehículo
        'vehicle_identification_type_id',
        'vehicle_identification',

        // Propietario
        'owner_document_type',
        'owner_document',
        'owner_nombre',
        'owner_apellido',
        'owner_direccion',
        'owner_departamento',
        'owner_ciudad',
        'owner_telefono',
        'owner_email',

        // Sistema
        'combustion_system_id',
        'application_type_id',
        'technology_id',
        'fecha_instalacion',

        'estado',
        'observacion_devolucion',

        // Revisión final
        'codigo',
        'observaciones_revisor',
        'revisor_id',
        'fecha_aprobacion',
        'revisada_en',
        'vence_el',
        'aviso_vencimiento_at',
    ];

    protected $casts = [
        'fecha_aprobacion' => 'datetime',
        'revisada_en' => 'datetime',
        'vence_el' => 'date',
        'fecha_instalacion' => 'date',
        'aviso_vencimiento_at' => 'datetime',
    ];

    // -------- Relaciones --------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function verificaciones()
    {
        return $this->hasMany(SolicitudVerificacion::class);
    }

    public function regulators()
    {
        return $this->hasMany(SolicitudRegulador::class);
    }

    public function cilindros()
    {
        return $this->hasMany(SolicitudCilindro::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function vehicleIdentificationType()
    {
        return $this->belongsTo(VehicleIdentificationType::class);
    }

    public function combustionSystem()
    {
        return $this->belongsTo(CombustionSystem::class);
    }

    public function applicationType()
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function technology()
    {
        return $this->belongsTo(Technology::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function adjuntos()
    {
        return $this->hasMany(SolicitudAdjunto::class, 'solicitud_id');
    }

    public function geolocalizacion()
    {
        return $this->hasOne(EvaluacionGeolocalizacion::class, 'solicitud_id');
    }

    public function verificacion()
    {
        return $this->hasOne(SolicitudVerificacion::class, 'solicitud_id')->latestOfMany();
    }

    /**
     * Evaluadores a quienes avisar de esta solicitud: si un evaluador ya la tomó (tiene verificación),
     * solo él; si nadie la ha tomado, los evaluadores asignados al taller. Siempre activos y con el rol.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function evaluadoresANotificar(): \Illuminate\Support\Collection
    {
        $encargado = $this->verificacion?->evaluador;

        if ($encargado && $encargado->activo !== false && $encargado->hasRole('evaluador')) {
            return collect([$encargado]);
        }

        return $this->user?->evaluadores()->role('evaluador')->activos()->get() ?? collect();
    }

    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'solicitud_id')->latest('created_at')->latest('id');
    }

    protected static function booted()
    {
        static::creating(function (Solicitud $solicitud) {
            $solicitud->user_id ??= Auth::id();
            $solicitud->estado ??= EstadoSolicitud::Pendiente->value;
        });

        // Una solicitud aprobada es el respaldo de un certificado emitido: no se borra.
        static::deleting(fn (Solicitud $solicitud) => ! $solicitud->estaAprobada());
    }

    // -------- Reglas de negocio --------

    public function estadoEnum(): ?EstadoSolicitud
    {
        return EstadoSolicitud::tryFrom((string) $this->estado);
    }

    public function esEvaluable(): bool
    {
        return in_array($this->estado, EstadoSolicitud::evaluables(), true);
    }

    public function esEditablePorTaller(): bool
    {
        return in_array($this->estado, EstadoSolicitud::editablesPorTaller(), true);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === EstadoSolicitud::Aprobada->value;
    }

    /**
     * Como en la plataforma anterior: el certificado sale cuando el evaluador envía la lista y el
     * revisor solo marca la solicitud como revisada o la devuelve. Apagado, el certificado espera
     * la aprobación del revisor.
     */
    public static function certificaAlEvaluar(): bool
    {
        return SystemSetting::enabled('certificado_al_evaluar');
    }

    /**
     * Cierra la solicitud como aprobada. Genera número y vigencia, salvo en el cambio de chip,
     * que se finaliza sin certificado. Si vuelve tras una devolución conserva número y fechas.
     */
    public function emitirCertificado(?int $revisorId = null): void
    {
        $sinCertificado = (bool) $this->serviceType?->esCambioDeChip();
        $expedicion = $this->fecha_aprobacion ?? now();

        DB::transaction(function () use ($sinCertificado, $expedicion, $revisorId) {
            $this->update([
                'estado' => EstadoSolicitud::Aprobada->value,
                'fecha_aprobacion' => $expedicion,
                'revisor_id' => $revisorId,
                'revisada_en' => $revisorId ? now() : null,
                ...($sinCertificado ? [] : [
                    'codigo' => $this->codigo ?? self::generarCodigo($this),
                    'vence_el' => $this->vence_el ?? self::calcularVencimiento($expedicion)->toDateString(),
                    'aviso_vencimiento_at' => null,
                ]),
            ]);

            $this->verificacion?->update(['estado' => 'aprobada']);
        });

        if ($sinCertificado) {
            return;
        }

        $placa = $this->placa();

        // Como en la plataforma anterior: el certificado va adjunto, en cada envío y en cada reenvío
        $datos = [
            'placa' => $placa,
            'detalle' => 'Certificado N.º '.$this->codigo.' · vigente hasta el '.$this->vence_el?->format('d/m/Y'),
            'boton_url' => \App\Filament\Resources\Solicituds\SolicitudResource::getUrl('view', ['record' => $this]),
        ];

        \App\Support\Correo::enviar($this->user, 'aprobacion', "Vehículo Certificado - Placa: {$placa}", 'emails.certificado', $datos, $this->id);

        if (filled($this->owner_email) && strcasecmp($this->owner_email, (string) $this->user?->email) !== 0) {
            \App\Support\Correo::enviar($this->owner_email, 'aprobacion', "Vehículo Certificado - Placa: {$placa}", 'emails.certificado', $datos, $this->id);
        }
    }

    /** Firma corta del número: evita que alguien recorra los certificados cambiando el número en el enlace. */
    public static function firmaVerificacion(string $codigo): string
    {
        return substr(hash_hmac('sha256', 'certificado|'.$codigo, (string) config('app.key')), 0, 16);
    }

    /** Página pública a la que lleva el QR del certificado. */
    public function urlVerificacion(): string
    {
        return route('certificado.verificar', ['codigo' => $this->codigo, 'f' => self::firmaVerificacion((string) $this->codigo)]);
    }

    /** PDF del certificado individual (el mismo que se descarga y el que va adjunto al correo). */
    public function pdfCertificado(): \Barryvdh\DomPDF\PDF
    {
        $this->load([
            'user', 'serviceType', 'combustionSystem', 'technology', 'vehicle.brand', 'vehicle.model', 'vehicle.type',
            'regulators.brand', 'cilindros.brand', 'verificacion.evaluador', 'verificacion.chip', 'revisor',
        ]);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.certificado', ['record' => $this])->setPaper('letter', 'landscape');
    }

    /** Certificada (o cambio de chip aceptado) que el revisor todavía no ha mirado. */
    public function estaPendienteDeRevision(): bool
    {
        return $this->estado === EstadoSolicitud::Evaluada->value || ($this->estaAprobada() && $this->revisada_en === null);
    }

    public function scopePendientesDeRevision(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('estado', EstadoSolicitud::Evaluada->value)
            ->orWhere(fn (Builder $a) => $a->where('estado', EstadoSolicitud::Aprobada->value)->whereNull('revisada_en')));
    }

    /** Aprobada y con número: el cambio de chip se aprueba sin certificado. */
    public function tieneCertificado(): bool
    {
        return $this->estaAprobada() && filled($this->codigo);
    }

    /** Se puede cancelar mientras no esté cerrada ni tenga certificado. */
    public function esCancelable(): bool
    {
        return blank($this->codigo) && ! in_array($this->estado, EstadoSolicitud::cerradas(), true);
    }

    public function cancelar(): void
    {
        $this->update(['estado' => EstadoSolicitud::Cancelada->value]);
    }

    /**
     * Avisos para el evaluador y el revisor en una revisión anual: equipos que no coinciden con
     * los de la última certificación del mismo vehículo. No bloquean.
     *
     * @return array{reguladores: array<int, string>, cilindros: array<int, string>}
     */
    public function alertasEquipos(): array
    {
        $alertas = ['reguladores' => [], 'cilindros' => []];

        if ($this->serviceType?->clase !== 'anual') {
            return $alertas;
        }

        $anterior = self::query()
            ->where('vehicle_identification', $this->vehicle_identification)
            ->where('id', '!=', $this->id)
            ->where('estado', EstadoSolicitud::Aprobada->value)
            ->latest('fecha_aprobacion')
            ->with(['regulators', 'cilindros'])
            ->first();

        if (! $anterior) {
            return $alertas;
        }

        $clave = fn ($equipo) => $equipo->brand_id.'|'.mb_strtoupper(trim((string) $equipo->numero_serie));
        $reguladores = $anterior->regulators->keyBy($clave);
        $cilindros = $anterior->cilindros->keyBy($clave);

        foreach ($this->regulators()->with('brand')->get() as $r) {
            if (! $reguladores->has($clave($r))) {
                $alertas['reguladores'][] = "El regulador ingresado en esta solicitud con serial {$r->numero_serie} y marca {$r->brand?->nombre}. La marca o el número de serie proporcionado en la solicitud no coincide con el registrado en la revisión anterior.";
            }
        }

        foreach ($this->cilindros()->with('brand')->get() as $c) {
            $previo = $cilindros->get($clave($c));
            $inicio = "El cilindro ingresado en esta solicitud con serial {$c->numero_serie} y marca {$c->brand?->nombre}.";

            if (! $previo) {
                $alertas['cilindros'][] = "{$inicio} La marca o el número de serie proporcionado en la solicitud no coincide con el registrado en la revisión anterior.";
            } elseif ((string) $previo->getRawOriginal('fecha_prueba') !== (string) $c->getRawOriginal('fecha_prueba')) {
                $alertas['cilindros'][] = "{$inicio} La fecha de prueba hidrostática en la solicitud no coincide con el registrado en la revisión anterior.";
            }
        }

        return $alertas;
    }

    /** Placa (o chasis) legible para correos y títulos. */
    public function placa(): string
    {
        return strtoupper($this->vehicle?->placa ?? $this->vehicle_identification ?? 'S/P');
    }

    /** ¿Puede este usuario ver la solicitud (y descargar su certificado)? */
    public function esVisiblePara(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin') || $this->user_id === $user->id) {
            return true;
        }

        if ($user->hasRole('evaluador')) {
            return $user->clientesAsignados()->whereKey($this->user_id)->exists();
        }

        if ($user->hasRole('revisor')) {
            return static::query()->visiblesParaRevisor($user)->whereKey($this->id)->exists();
        }

        return false;
    }

    /**
     * Un revisor ve lo evaluado por los evaluadores que tiene asignados
     * y también lo de evaluadores sin revisor asignado (para que nada quede huérfano).
     */
    public function scopeVisiblesParaRevisor($query, User $revisor)
    {
        $asignados = $revisor->evaluadoresSupervisados()->pluck('users.id');
        $conRevisor = DB::table('evaluador_revisor')->select('evaluador_id');

        return $query->whereHas('verificaciones', function ($q) use ($asignados, $conRevisor) {
            $q->whereIn('evaluador_id', $asignados)
                ->orWhereNotIn('evaluador_id', $conRevisor);
        });
    }

    // -------- Vigencia del certificado --------

    /** Fecha de vencimiento para un certificado aprobado hoy (meses configurables, 12 por defecto). */
    public static function calcularVencimiento(?\DateTimeInterface $aprobacion = null): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::instance($aprobacion ?? now())->addMonthsNoOverflow(SystemSetting::vigencia()['meses']);
    }

    public function estaVencida(): bool
    {
        return $this->estaAprobada() && $this->vence_el !== null && $this->vence_el->isPast() && ! $this->vence_el->isToday();
    }

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function diasParaVencer(): ?int
    {
        return $this->vence_el ? (int) now()->startOfDay()->diffInDays($this->vence_el, false) : null;
    }

    /**
     * Certificados aprobados que vencen dentro de $dias (o ya vencieron) y que todavía
     * no tienen una solicitud más reciente para el mismo vehículo (es decir, no se han renovado).
     */
    public function scopePorRenovar(Builder $query, int $dias): Builder
    {
        return $query
            ->where('estado', EstadoSolicitud::Aprobada->value)
            ->whereNotNull('vence_el')
            ->whereDate('vence_el', '<=', now()->addDays($dias)->toDateString())
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('solicituds as posterior')
                    ->whereColumn('posterior.vehicle_identification', 'solicituds.vehicle_identification')
                    ->whereColumn('posterior.id', '>', 'solicituds.id');
            });
    }

    // -------- Auditoría --------

    /** Historial para la línea de tiempo: el taller solo ve la creación y los cambios de estado. */
    public function historialPara(?User $user)
    {
        return $this->actividades()
            ->with('user:id,name')
            ->when(! $user?->hasAnyRole(['admin', 'evaluador', 'revisor']), fn ($q) => $q->whereIn('evento', Actividad::EVENTOS_PUBLICOS))
            ->limit(200)
            ->get();
    }

    protected function solicitudIdAuditoria(): ?int
    {
        return $this->id;
    }

    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return $evento === 'actualizado' && array_key_exists('estado', $cambios) ? 'estado' : $evento;
    }

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        if ($evento === 'creado') {
            return 'Solicitud creada para el vehículo '.$this->placa();
        }

        if ($evento === 'eliminado') {
            return 'Solicitud de la placa '.$this->placa().' eliminada';
        }

        if (! array_key_exists('estado', $cambios)) {
            return 'Datos de la solicitud modificados: '.implode(', ', array_keys($cambios));
        }

        $texto = EstadoSolicitud::labelDe($cambios['estado']['antes']).' → '.EstadoSolicitud::labelDe($cambios['estado']['despues']);

        if (filled($cambios['observacion_devolucion']['despues'] ?? null)) {
            $texto .= '. Motivo: '.$cambios['observacion_devolucion']['despues'];
        }

        if ($this->estado === EstadoSolicitud::CorreccionTecnica->value && filled($cambios['observaciones_revisor']['despues'] ?? null)) {
            $texto .= '. Motivo: '.$cambios['observaciones_revisor']['despues'];
        }

        if (filled($cambios['codigo']['despues'] ?? null)) {
            $texto .= '. Certificado N.º '.$cambios['codigo']['despues'];
        }

        return $texto;
    }

    /**
     * Número de certificado: consecutivo global, como en la plataforma anterior (161271, 161272…).
     * El siguiente número se guarda en Configuración y se toma con bloqueo para que dos evaluadores
     * que envían a la vez no reciban el mismo. Debe llamarse dentro de una transacción.
     */
    public static function generarCodigo(?self $solicitud = null): string
    {
        DB::table('system_settings')->insertOrIgnore(['key' => 'consecutivo_certificado', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $ajuste = DB::table('system_settings')->where('key', 'consecutivo_certificado')->lockForUpdate()->first();
        $numero = max(1, (int) (json_decode((string) $ajuste->meta, true)['siguiente'] ?? 1));

        // Por si el contador quedó por detrás de un número ya usado (datos migrados)
        while (self::where('codigo', (string) $numero)->exists()) {
            $numero++;
        }

        DB::table('system_settings')->where('key', 'consecutivo_certificado')->update([
            'meta' => json_encode(['siguiente' => $numero + 1]),
            'updated_at' => now(),
        ]);

        return (string) $numero;
    }

    public static function siguienteNumeroCertificado(): int
    {
        return max(1, (int) (SystemSetting::meta('consecutivo_certificado', 'siguiente') ?? 1));
    }
}
