<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property string $nombre_adjunto
 * @property string|null $ruta_archivo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Solicitud $solicitud
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereNombreAdjunto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereRutaArchivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereSolicitudId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SolicitudAdjunto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SolicitudAdjunto extends Model
{
    protected $fillable = ['solicitud_id', 'nombre_adjunto', 'ruta_archivo'];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }
}