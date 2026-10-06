<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $nombre
 * @property int $activo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VehicleModel> $models
 * @property-read int|null $models_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand whereActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleBrand whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VehicleBrand extends Model
{
    protected $fillable = ['nombre', 'activo'];

    public function models()
    {
        return $this->hasMany(VehicleModel::class, 'brand_id');
    }

    public static function cached()
    {
        return Cache::remember(
            'vehicle_brands',
            now()->addDay(),
            fn () => self::orderBy('nombre')->pluck('nombre', 'id')
        );
    }
}
