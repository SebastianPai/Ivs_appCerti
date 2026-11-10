<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/** Tipos de vehículo de la plataforma anterior (allá había 35 registros; estos son los nombres distintos). */
class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'AUTOMOVIL', 'PESADO', 'CAMPERO', 'CAMIONETA DE PLATON', 'CAMIONETA ESTACAS', 'CAMION', 'VOLQUETA', 'BUSETA',
            'MICROBUS', 'CAMIONETA', 'BUS', 'MONTACARGA', 'TRACTOCAMION', 'MOTOCARRO', 'FURGON', 'WAGON', 'GRUA',
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate([
                'nombre' => $type,
            ]);
        }

    }
}
