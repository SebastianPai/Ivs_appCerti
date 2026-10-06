<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApplicationType;

class ApplicationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = ['Taxis', 'Particular', 'Carga liviana', 'Carga pesada'];

        foreach ($items as $item) {
            ApplicationType::firstOrCreate(['nombre' => $item]);
        }
    }
}
