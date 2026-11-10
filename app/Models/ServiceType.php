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

    /** Clase del servicio: decide cómo lo tratan los exportables SICOM. */
    public const CLASES = [
        'inicial' => 'Inicial (conversión)',
        'anual' => 'Revisión anual',
        'cambio_chip' => 'Cambio de chip',
    ];

    /** Documentos y equipos que el taller debe traer; cada tipo de servicio exige todos o solo algunos. */
    public const REQUISITOS = [
        'preconversion' => 'Anexo de preconversión',
        'posconversion' => 'Anexo de posconversión',
        'conformidad' => 'Certificado de conformidad',
        'acta_desmonte' => 'Acta de desmonte',
        'prueba_hidrostatica' => 'Prueba hidrostática',
        'reguladores' => 'Al menos un regulador',
        'cilindros' => 'Al menos un cilindro',
    ];

    protected $fillable = ['nombre', 'descripcion', 'activo', 'clase', 'codigo_exportado', 'nombre_exportado', 'chip_nuevo', 'fotos_obligatorias', 'requisitos'];

    /** Sin lista definida, el tipo exige todo. */
    public function exige(string $requisito): bool
    {
        return $this->requisitos === null || in_array($requisito, $this->requisitos, true);
    }

    public function esInicial(): bool
    {
        return $this->clase === 'inicial';
    }

    public function esCambioDeChip(): bool
    {
        return $this->clase === 'cambio_chip';
    }

    /** Código y nombre del tipo de certificación en el exportable SICOM por certificado. */
    public function codigoExportado(): string
    {
        return $this->codigo_exportado ?: (string) $this->id;
    }

    public function nombreExportado(): string
    {
        return $this->nombre_exportado ?: (string) $this->nombre;
    }

    protected $casts = [
        'activo' => 'boolean',
        'chip_nuevo' => 'boolean',
        'fotos_obligatorias' => 'boolean',
        'requisitos' => 'array',
    ];
}
