<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegulatorBrand whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RegulatorBrand extends Model
{
    use HasFactory;

    protected $table = 'regulator_brands';

    protected $fillable = [
        'nombre',
    ];
}
