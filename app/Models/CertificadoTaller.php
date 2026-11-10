<?php

namespace App\Models;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Certificado mensual de un taller: los vehículos que se le certificaron en el mes.
 *
 * @property int $id
 * @property int $taller_id
 * @property int|null $generador_id
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon $fecha_fin
 * @property int $cantidad
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class CertificadoTaller extends Model
{
    /** Datos fijos del organismo que salen en el PDF. Logos, firma y firmante: App\Support\MarcaCertificado. */
    public const ORGANISMO = [
        'nit' => '900.805.525-5',
        'direccion' => 'Calle 8 # 47 - 136 - Nueva Tequendama',
        'telefono' => '(602) 3087609',
        'formato' => 'FO-OP-106 Revisión 8 (04/11/2025)',
    ];

    protected $table = 'certificados_taller';

    protected $fillable = [
        'taller_id',
        'generador_id',
        'fecha_inicio',
        'fecha_fin',
        'cantidad',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function taller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taller_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generador_id');
    }

    /**
     * Como en la plataforma anterior: solicitudes del taller con número de certificado expedido en
     * el rango, revisadas o no y devueltas incluidas. El cambio de chip no entra porque no tiene número.
     */
    public static function vehiculos(int $tallerId, \DateTimeInterface|string $inicio, \DateTimeInterface|string $fin): Builder
    {
        return Solicitud::query()
            ->where('user_id', $tallerId)
            ->whereNotNull('codigo')
            ->whereBetween('fecha_aprobacion', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fin)->endOfDay()])
            ->with('vehicle')
            ->orderBy('fecha_aprobacion')
            ->orderBy('id');
    }

    /** El PDF se arma con los vehículos que cumplen hoy, no con una copia guardada. */
    public function pdf(): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.certificado-taller', [
            'certificado' => $this->loadMissing(['taller', 'generador']),
            'vehiculos' => self::vehiculos($this->taller_id, $this->fecha_inicio, $this->fecha_fin)->get(),
        ])->setPaper('letter');
    }
}
