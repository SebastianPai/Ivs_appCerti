<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleBrand;

class VehicleBrandSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Chevrolet', 'Mazda', 'Toyota', 'Kia', 'Hyundai', 'Renault'];

        foreach ($items as $item) {
            VehicleBrand::firstOrCreate(['nombre' => $item]);
        }
    }
}
