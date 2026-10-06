<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int $evaluador_id
 * @property numeric|null $lat
 * @property numeric|null $lng
 * @property numeric|null $accuracy
 * @property bool $conflicto_interes
 * @property string $estado
 * @property \Illuminate\Support\Carbon|null $verificada_en
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $datos_checklist
 * @property string|null $id_chip
 * @property string|null $observaciones
 * @property-read \App\Models\Chip|null $chip
 * @property-read \App\Models\User $evaluador
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VerificacionFoto> $fotos
 * @property-read int|null $fotos_count
 * @property-read \App\Models\Solicitud $solicitud
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereConflictoInteres($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereDatosChecklist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereEvaluadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereIdChip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereLng($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereObservaciones($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereSolicitudId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudVerificacion whereVerificadaEn($value)
 * @mixin \Eloquent
 */
class SolicitudVerificacion extends Model
{
    protected $table = 'solicitud_verificaciones';
    
    protected $fillable = [
        'solicitud_id',
        'evaluador_id',
        'datos_checklist', // <--- Importante
        'lat',
        'lng',
        'accuracy',
        'conflicto_interes',
        'estado',
        'verificada_en',
        'ip',
        'user_agent',
        'id_chip',
        'observaciones',
    ];

    protected $casts = [
        'conflicto_interes' => 'boolean',
        'verificada_en' => 'datetime',
        'datos_checklist' => 'array',
    ];

    // Relación para las fotos de la inspección
    public function fotos(): HasMany
    {
        return $this->hasMany(VerificacionFoto::class, 'solicitud_verificacion_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function chip(): BelongsTo
    {
        return $this->belongsTo(Chip::class, 'id_chip');
    }
}