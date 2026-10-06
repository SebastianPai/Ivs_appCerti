<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int|null $brand_id
 * @property string|null $numero_serie
 * @property int|null $capacidad
 * @property string|null $fecha_fabricacion
 * @property string|null $fecha_prueba
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CylinderBrand|null $brand
 * @property-read \App\Models\Solicitud $solicitud
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereCapacidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereFechaFabricacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereFechaPrueba($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereNumeroSerie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereSolicitudId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudCilindro whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SolicitudCilindro extends Model
{
    use HasFactory;

    protected $table = 'solicitud_cilindros';

    protected $fillable = [
        'solicitud_id',
        'brand_id',
        'numero_serie',
        'capacidad',
        'fecha_fabricacion',
        'fecha_prueba',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function brand()
    {
        return $this->belongsTo(CylinderBrand::class, 'brand_id');
    }
}
