<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los exportables SICOM tratan distinto cada clase de servicio (inicial, anual, cambio de chip)
 * y llevan un código y un nombre propios del tipo de certificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->string('clase', 20)->nullable()->after('descripcion');
            $table->string('codigo_exportado', 10)->nullable()->after('clase');
            $table->string('nombre_exportado')->nullable()->after('codigo_exportado');
        });

        // Punto de partida para los tipos que ya existen; el admin lo ajusta en Tipos de servicio
        foreach (['%chip%' => 'cambio_chip', '%anual%' => 'anual', '%inicial%' => 'inicial', 'conversi%' => 'inicial'] as $patron => $clase) {
            DB::table('service_types')->whereNull('clase')->whereRaw('LOWER(nombre) LIKE ?', [$patron])->update(['clase' => $clase]);
        }
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn(['clase', 'codigo_exportado', 'nombre_exportado']);
        });
    }
};
