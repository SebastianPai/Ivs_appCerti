<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Chip;

class ChipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Chip::updateOrCreate(
            ['codigo' => 'CHIP-001'],
            ['activo' => true, 'descripcion' => 'Chip principal']
        );
    }
}
