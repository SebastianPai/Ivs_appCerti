<?php

namespace Database\Seeders;

use App\Support\CatalogoVehiculos;
use Illuminate\Database\Seeder;

/** Marcas y líneas de vehículo de la plataforma anterior (database/data/vehiculos.json). */
class VehicleBrandSeeder extends Seeder
{
    public function run(): void
    {
        CatalogoVehiculos::importar();
    }
}
