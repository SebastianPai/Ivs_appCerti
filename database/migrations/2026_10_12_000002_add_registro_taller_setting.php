<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Como en la plataforma anterior: un taller con el registro de Industria y Comercio vencido (o
 * sin fecha) no entra hasta cargarlo y que el admin le asigne la fecha. Se puede apagar en Configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('system_settings')->where('key', 'registro_taller_required')->exists()) {
            DB::table('system_settings')->insert(['key' => 'registro_taller_required', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'registro_taller_required')->delete();
    }
};
