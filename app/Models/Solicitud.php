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

        'estado',
        'observacion_devolucion',

        // Revisión final
        'codigo',
        'observaciones_revisor',
        'revisor_id',
        'fecha_aprobacion',
        'vence_el',
        'aviso_vencimiento_at',
    ];

    protected $casts = [
        'fecha_aprobacion' => 'datetime',
        'vence_el' => 'date',
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

    /** Número de certificado: IVS-AAAA-000123 */
    public static function generarCodigo(self $solicitud): string
    {
        return sprintf('IVS-%s-%06d', now()->format('Y'), $solicitud->id);
    }
}
