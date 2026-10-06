<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $solicitud_verificacion_id
 * @property string $nombre_foto
 * @property string|null $ruta_foto
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SolicitudVerificacion $verificacion
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereNombreFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereRutaFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereSolicitudVerificacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificacionFoto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VerificacionFoto extends Model {
    protected $table = 'verificacion_fotos';
    protected $fillable = ['solicitud_verificacion_id', 'nombre_foto', 'ruta_foto'];

    public function verificacion(): BelongsTo {
        return $this->belongsTo(SolicitudVerificacion::class, 'solicitud_verificacion_id');
    }
}