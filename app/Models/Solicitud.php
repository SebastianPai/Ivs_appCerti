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
    use HasFactory;

    protected $table = 'solicituds';

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
    ];

    protected $casts = [
        'fecha_aprobacion' => 'datetime',
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

    /** Número de certificado: IVS-AAAA-000123 */
    public static function generarCodigo(self $solicitud): string
    {
        return sprintf('IVS-%s-%06d', now()->format('Y'), $solicitud->id);
    }
}
