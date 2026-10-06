<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CombustionSystem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CombustionSystem extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];
}
