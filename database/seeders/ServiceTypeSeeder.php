<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceType;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Conversión a GNV',
            'Conversión a GLP',
            'Revisión anual',
            'Revisión quinquenal',
        ];

        foreach ($items as $item) {
            ServiceType::firstOrCreate(['nombre' => $item]);
        }
    }
}
