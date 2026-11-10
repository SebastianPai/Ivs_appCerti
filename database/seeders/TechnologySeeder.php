<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Technology;

class TechnologySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'TERCERA',
            'CUARTA',
            'QUINTA',
        ];

        foreach ($items as $item) {
            Technology::firstOrCreate(['nombre' => $item]);
        }
    }
}
