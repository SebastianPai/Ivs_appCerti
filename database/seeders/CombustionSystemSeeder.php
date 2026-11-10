<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CombustionSystem;

class CombustionSystemSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['CARBURADO', 'INYECTADO'];

        foreach ($items as $item) {
            CombustionSystem::firstOrCreate(['nombre' => $item]);
        }
    }
}
