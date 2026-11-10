<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Como en la plataforma anterior, el certificado puede emitirse cuando el evaluador envía la
 * lista; la revisión pasa a ser una marca posterior (revisada_en) y no un estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicituds', function (Blueprint $table) {
            $table->timestamp('revisada_en')->nullable()->after('fecha_aprobacion');
        });

        // Lo aprobado hasta hoy lo aprobó un revisor: ya está revisado
        DB::table('solicituds')->where('estado', 'aprobada')->update(['revisada_en' => DB::raw('fecha_aprobacion')]);

        // Modo de la plataforma anterior activado; el admin lo puede apagar en Configuración
        if (! DB::table('system_settings')->where('key', 'certificado_al_evaluar')->exists()) {
            DB::table('system_settings')->insert(['key' => 'certificado_al_evaluar', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'certificado_al_evaluar')->delete();

        Schema::table('solicituds', function (Blueprint $table) {
            $table->dropColumn('revisada_en');
        });
    }
};
