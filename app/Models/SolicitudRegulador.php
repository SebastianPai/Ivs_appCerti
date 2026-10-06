<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int|null $brand_id
 * @property string $numero_serie
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\RegulatorBrand|null $brand
 * @property-read \App\Models\Solicitud $solicitud
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereNumeroSerie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereSolicitudId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudRegulador whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SolicitudRegulador extends Model
{
    use HasFactory;

    protected $table = 'solicitud_reguladores';

    protected $fillable = [
        'solicitud_id',
        'brand_id',
        'numero_serie',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function brand()
    {
        return $this->belongsTo(RegulatorBrand::class, 'brand_id');
    }
}
