<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Equipos entregados a un usuario: dos teléfonos móviles y el flexómetro (ver la migración). */
class InventarioUsuario extends Model
{
    protected $table = 'inventario_usuarios';

    public const CAMPOS = [
        'tiene_movil1', 'movil1_marca', 'movil1_modelo', 'movil1_serial', 'movil1_tiene_cargador', 'movil1_tiene_caja',
        'tiene_movil2', 'movil2_marca', 'movil2_modelo', 'movil2_serial', 'movil2_tiene_cargador', 'movil2_tiene_caja',
        'flexometro_serial', 'flexometro_fecha_entrega', 'flexometro_fecha_verificacion',
    ];

    protected $fillable = self::CAMPOS;

    protected function casts(): array
    {
        return [
            'tiene_movil1' => 'boolean',
            'movil1_tiene_cargador' => 'boolean',
            'movil1_tiene_caja' => 'boolean',
            'tiene_movil2' => 'boolean',
            'movil2_tiene_cargador' => 'boolean',
            'movil2_tiene_caja' => 'boolean',
            'flexometro_fecha_entrega' => 'date',
            'flexometro_fecha_verificacion' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
