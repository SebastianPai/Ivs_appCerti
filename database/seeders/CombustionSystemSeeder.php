<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CombustionSystem;

class CombustionSystemSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Carburado', 'Inyección secuencial', 'Inyección directa'];

        foreach ($items as $item) {
            CombustionSystem::firstOrCreate(['nombre' => $item]);
        }
    }
}
