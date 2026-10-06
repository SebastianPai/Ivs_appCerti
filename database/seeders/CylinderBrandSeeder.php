<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CylinderBrand;

class CylinderBrandSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Luxfer', 'Faber', 'Aspro', 'Cylinders Inc'];

        foreach ($items as $item) {
            CylinderBrand::firstOrCreate(['nombre' => $item]);
        }
    }
}
