<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $brand_id
 * @property int $model_id
 * @property int $vehicle_type_id
 * @property int $year
 * @property string|null $vin
 * @property string|null $placa
 * @property string|null $motor
 * @property string $fuel_base
 * @property numeric|null $engine_displacement
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\VehicleBrand $brand
 * @property-read \App\Models\VehicleModel $model
 * @property-read \App\Models\Solicitud|null $solicitud
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Solicitud> $solicitudes
 * @property-read int|null $solicitudes_count
 * @property-read \App\Models\VehicleType $type
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereEngineDisplacement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereFuelBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereMotor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle wherePlaca($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereVehicleTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereVin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vehicle whereYear($value)
 * @mixin \Eloquent
 */
class Vehicle extends Model
{
    protected $fillable = [
        'brand_id',
        'model_id',
        'vehicle_type_id',
        'year',
        'vin',
        'placa',
        'motor',
        'fuel_base',
        'engine_displacement',
    ];

    public function brand()
    {
        return $this->belongsTo(VehicleBrand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function type()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class);
    }

    public function solicitud()
    {
        return $this->hasOne(Solicitud::class);
    }


    protected static function booted()
    {
        static::saving(function ($vehicle) {
            $vehicle->placa = $vehicle->placa ? strtoupper(trim($vehicle->placa)) : null;
            $vehicle->vin = $vehicle->vin ? strtoupper(trim($vehicle->vin)) : null;
        });
    }

}

