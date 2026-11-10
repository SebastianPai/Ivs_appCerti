<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje de Contáctenos.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nombre
 * @property string $email
 * @property string $asunto
 * @property string $mensaje
 * @property \Illuminate\Support\Carbon|null $gestionado_en
 * @property int|null $gestionado_por
 */
class MensajeContacto extends Model
{
    protected $table = 'mensajes_contacto';

    protected $fillable = ['user_id', 'nombre', 'email', 'asunto', 'mensaje', 'gestionado_en', 'gestionado_por'];

    protected $casts = ['gestionado_en' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestionado_por');
    }
}
