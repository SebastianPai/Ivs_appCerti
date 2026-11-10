<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApplicationType;

class ApplicationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['TRANSFORMADO DEDICADO', 'BICOMBUSTIBLE', 'DUAL'];

        foreach ($items as $item) {
            ApplicationType::firstOrCreate(['nombre' => $item]);
        }
    }
}
