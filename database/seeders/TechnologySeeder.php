<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Technology;

class TechnologySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Tecnología de 3ra generación',
            'Tecnología de 4ta generación',
            'Inyección multipunto',
        ];

        foreach ($items as $item) {
            Technology::firstOrCreate(['nombre' => $item]);
        }
    }
}
