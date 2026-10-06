<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $activo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ServiceType extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
