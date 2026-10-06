<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property bool $activo
 * @property string|null $descripcion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SolicitudVerificacion> $verificaciones
 * @property-read int|null $verificaciones_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chip whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Chip extends Model
{
    use Concerns\Auditable;

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        return "Chip {$this->codigo} ".match ($evento) {
            'creado' => 'registrado',
            'eliminado' => 'eliminado',
            default => 'modificado: '.implode(', ', array_keys($cambios)),
        };
    }

    protected $fillable = [
        'codigo',
        'activo',
        'descripcion',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function verificaciones(): HasMany
    {
        return $this->hasMany(SolicitudVerificacion::class, 'id_chip');
    }
}

