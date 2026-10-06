<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CylinderBrand whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CylinderBrand extends Model
{
    use HasFactory;

    protected $table = 'cylinder_brands';

    protected $fillable = [
        'nombre',
    ];

    protected $casts = [
        'fecha_prueba' => 'date',
        'fecha_fabricacion' => 'date',
    ];
}
