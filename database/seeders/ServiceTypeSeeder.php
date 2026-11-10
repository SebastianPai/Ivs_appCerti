<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * Tipos de servicio de la plataforma anterior. El código es el id que tenían allá:
 * es el que viaja como id_tipo_certificado en el exportable SICOM por certificado.
 */
class ServiceTypeSeeder extends Seeder
{
    // [código, nombre, nombre en exportados, clase, convenio, activo]
    private const TIPOS = [
        ['1', 'INICIAL', 'REVISION INICIAL', 'inicial', 'particular', true],
        ['2', 'REVISION ANUAL', 'REVISION ANUAL', 'anual', 'particular', true],
        ['7', 'CAMBIO DE CHIP', 'CAMBIO DE CHIP', 'cambio_chip', 'particular', true],
        ['4', 'REVISION ANUAL + CAMBIO', 'REVISION ANUAL', 'anual', 'particular', true],
        ['5', 'REVISION ANUAL + BONO 300', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['6', 'REVISION ANUAL + BONO 390', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['8', 'REVISION ANUAL + CAMBIO + BONO 300', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['9', 'REVISION ANUAL + CAMBIO + BONO 390', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['10', 'REVISION GAZEL VENCIDO', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['11', 'REVISION GAZEL ALTO CONSUMO', 'REVISION ANUAL', 'anual', 'convenio', false],
        ['12', 'INICIAL GAZEL', 'REVISION INICIAL', 'inicial', 'convenio', false],
        ['13', 'GARANTIA DE CHIP', 'GARANTIA DE CHIP', null, 'particular', true],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as [$codigo, $nombre, $exportado, $clase, $convenio, $activo]) {
            ServiceType::firstOrCreate(['nombre' => $nombre], [
                'codigo_exportado' => $codigo,
                'nombre_exportado' => $exportado,
                'clase' => $clase,
                'descripcion' => $convenio,
                'activo' => $activo,
                // Chip nuevo: inicial, cambio de chip, anual + cambio y garantía
                'chip_nuevo' => in_array($codigo, ['1', '4', '7', '8', '9', '13'], true),
                'fotos_obligatorias' => $clase === 'inicial',
                'requisitos' => match ($codigo) {
                    '7' => ['preconversion', 'posconversion', 'conformidad', 'acta_desmonte', 'prueba_hidrostatica'],
                    '13' => ['posconversion'],
                    default => null,
                },
            ]);
        }
    }
}
