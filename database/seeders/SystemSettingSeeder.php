<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: no pisa la configuración que el admin ya haya cambiado
        // Mismos valores que tiene hoy la plataforma anterior en Configuración General
        SystemSetting::firstOrCreate(['key' => 'chip_required'], ['enabled' => true, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'checklist_prellenado'], ['enabled' => true, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'sicom_consulta_evaluacion'], ['enabled' => false, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'sicom_consulta_diagnostico'], ['enabled' => true, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'certificado_al_evaluar'], ['enabled' => true, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'registro_taller_required'], ['enabled' => true, 'meta' => null]);
        SystemSetting::firstOrCreate(['key' => 'servicios_por_taller'], ['enabled' => true, 'meta' => null]);

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
