<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Catálogos
            ServiceTypeSeeder::class,
            VehicleIdentificationTypeSeeder::class,
            VehicleBrandSeeder::class,
            VehicleTypeSeeder::class,
            // VehicleCatalogSeeder hace miles de peticiones a la API de NHTSA: correrlo a mano si se necesita
            // php artisan db:seed --class=VehicleCatalogSeeder
            CombustionSystemSeeder::class,
            ApplicationTypeSeeder::class,
            TechnologySeeder::class,
            RegulatorBrandSeeder::class,
            CylinderBrandSeeder::class,

            // Seguridad y configuración
            RoleSeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
            ChipSeeder::class,
        ]);
    }
}
