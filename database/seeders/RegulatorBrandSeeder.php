<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RegulatorBrand;

class RegulatorBrandSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Lovato', 'Tomasetto', 'BRC', 'OMVL'];

        foreach ($items as $item) {
            RegulatorBrand::firstOrCreate(['nombre' => $item]);
        }
    }
}
