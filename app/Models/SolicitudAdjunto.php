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
    use Concerns\Auditable;

    protected $fillable = ['solicitud_id', 'nombre_adjunto', 'ruta_archivo'];

    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return 'documento';
    }

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        $nombre = \App\Filament\Resources\Solicituds\Schemas\AttachmentForm::etiqueta($this->nombre_adjunto);

        return match (true) {
            $evento === 'eliminado' => "Documento «{$nombre}» eliminado",
            blank($this->ruta_archivo) && $evento === 'creado' => null, // casilla vacía, sin archivo
            blank($this->ruta_archivo) => "Documento «{$nombre}» quitado",
            $evento === 'creado' || blank($cambios['ruta_archivo']['antes'] ?? null) => "Documento «{$nombre}» cargado",
            array_key_exists('ruta_archivo', $cambios) => "Documento «{$nombre}» reemplazado",
            default => null,
        };
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }
}