<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VehicleType extends Model
{
    protected $fillable = ['nombre'];
}
