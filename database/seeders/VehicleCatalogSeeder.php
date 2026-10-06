<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;

class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obtener marcas
        $response = Http::get(
            'https://vpic.nhtsa.dot.gov/api/vehicles/getallmakes',
            ['format' => 'json']
        );

        if (!$response->successful()) {
            $this->command->error('No se pudo conectar a NHTSA');
            return;
        }

        $makes = collect($response->json('Results'));

        foreach ($makes as $make) {

            $brand = VehicleBrand::firstOrCreate([
                'nombre' => strtoupper($make['Make_Name']),
            ]);

            // 2. Modelos por año (2000 → actual)
            for ($year = 2000; $year <= now()->year; $year++) {

                $modelsResponse = Http::get(
                    "https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/{$make['Make_Name']}/modelyear/{$year}",
                    ['format' => 'json']
                );

                if (!$modelsResponse->successful()) {
                    continue;
                }

                foreach ($modelsResponse->json('Results') ?? [] as $model) {
                    VehicleModel::updateOrCreate(
                        [
                            'brand_id' => $brand->id,
                            'nombre'   => strtoupper($model['Model_Name']),
                        ],
                        [
                            'year_start' => $year,
                            'year_end'   => $year,
                        ]
                    );
                }

                usleep(200000); // 0.2s para no abusar
            }
        }

        $this->command->info('Catálogo NHTSA cargado correctamente');
    }
}
