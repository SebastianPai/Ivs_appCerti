<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\VehicleBrand;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $brand_id
 * @property string $nombre
 * @property int|null $year_start
 * @property int|null $year_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read VehicleBrand $brand
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Solicitud> $solicitudes
 * @property-read int|null $solicitudes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Vehicle> $vehicles
 * @property-read int|null $vehicles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereYearEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleModel whereYearStart($value)
 * @mixin \Eloquent
 */
class VehicleModel extends Model
{
    protected $table = 'vehicle_models';

    protected $fillable = [
        'brand_id',
        'nombre',
        'year_start',
        'year_end',
    ];

    public function brand()
    {
        return $this->belongsTo(VehicleBrand::class, 'brand_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'model_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class);
    }



    public static function cachedByBrand($brandId)
        {
            return Cache::remember(
                "vehicle_models_brand_{$brandId}",
                now()->addDays(30),
                fn () => self::where('brand_id', $brandId)
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
            );
        }


}

