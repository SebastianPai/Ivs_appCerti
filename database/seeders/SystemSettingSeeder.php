<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: no pisa la configuración que el admin ya haya cambiado
        SystemSetting::firstOrCreate(['key' => 'chip_required'], ['enabled' => false, 'meta' => null]);

        SystemSetting::firstOrCreate(['key' => 'restrict_pc_access'], [
            'enabled' => false,
            'meta' => ['roles' => [
                'cliente' => ['block_all' => false, 'restrict_pc' => false],
                'evaluador' => ['block_all' => false, 'restrict_pc' => false],
                'revisor' => ['block_all' => false, 'restrict_pc' => false],
            ]],
        ]);
    }
}
