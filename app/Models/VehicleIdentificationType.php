<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VehicleIdentificationType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VehicleIdentificationType extends Model
{
    use HasFactory;

    protected $table = 'identification_types'; // ← nombre REAL de la tabla

    protected $fillable = ['nombre']; // ← columna REAL
}
