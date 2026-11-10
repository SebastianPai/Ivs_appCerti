<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Consulta de SICOM por los datos abiertos de MinEnergía: «Consulta Vehículos con Instalación de
 * GNCV (AUTOMATIZADO)» en datos.gov.co. La plataforma anterior leía la página de SICOM desde el
 * servidor; este conjunto trae lo mismo (placa, VIN, chip, estado e instalación) sin credenciales.
 */
class SicomAbierto
{
    private const URL = 'https://www.datos.gov.co/resource/5ue6-jtmx.json';

    /**
     * Registro más reciente que cumple el filtro (placa, vin o idrom_chip), o null si no existe.
     *
     * @param  array<string, string>  $filtro
     * @return array<string, string>|null
     *
     * @throws \Throwable si el servicio no responde
     */
    public static function buscar(array $filtro): ?array
    {
        return Http::timeout(8)
            ->get(self::URL, [...$filtro, '$order' => 'fecha_instalacion DESC', '$limit' => 1])
            ->throw()
            ->json(0);
    }

    public static function porChip(string $idrom): ?array
    {
        return self::buscar(['idrom_chip' => $idrom]);
    }

    public static function porVehiculo(string $identificacion, bool $esChasis = false): ?array
    {
        return self::buscar([$esChasis ? 'vin' : 'placa' => $identificacion]);
    }

    public static function municipio(array $fila): string
    {
        return trim(($fila['municipio_instalacion'] ?? '').', '.($fila['departamento_instalacion'] ?? ''), ', ');
    }
}
