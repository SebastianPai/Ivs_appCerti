<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int $evaluador_id
 * @property numeric $latitud
 * @property numeric $longitud
 * @property numeric|null $precision_m
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $registrado_en
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereEvaluadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereLatitud($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereLongitud($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion wherePrecisionM($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereRegistradoEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereSolicitudId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluacionGeolocalizacion whereUserAgent($value)
 * @mixin \Eloquent
 */
class EvaluacionGeolocalizacion extends Model
{
    use Concerns\Auditable;

    protected $table = 'evaluacion_geolocalizaciones';

    protected array $noAuditar = ['user_agent'];

    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return 'inspeccion';
    }

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        if ($evento === 'eliminado') {
            return null;
        }

        $precision = $this->precision_m !== null ? ' (±'.round((float) $this->precision_m).' m)' : '';

        return "Ubicación GPS registrada en {$this->latitud}, {$this->longitud}{$precision}";
    }

    protected $fillable = [
        'solicitud_id',
        'evaluador_id',
        'latitud',
        'longitud',
        'precision_m',
        'ip',
        'user_agent',
        'registrado_en',
    ];

    public $timestamps = true;
}

