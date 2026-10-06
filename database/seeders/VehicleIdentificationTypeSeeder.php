<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleIdentificationType;

class VehicleIdentificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Placa', 'Chasis'];

        foreach ($items as $item) {
            VehicleIdentificationType::firstOrCreate(['nombre' => $item]);
        }
    }
}
