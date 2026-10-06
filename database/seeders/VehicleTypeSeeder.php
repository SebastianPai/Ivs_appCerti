<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleType;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Automóvil',
            'Camioneta',
            'Camión',
            'Bus',
            'Motocicleta',
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate([
                'nombre' => $type,
            ]);
        }

    }
}
