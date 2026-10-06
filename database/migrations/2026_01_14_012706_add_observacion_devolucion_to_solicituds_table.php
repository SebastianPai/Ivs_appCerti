<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicituds', function (Blueprint $table) {
            // Guardaremos aquí el texto que escribe el evaluador para mostrarlo en el correo
            $table->text('observacion_devolucion')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('solicituds', function (Blueprint $table) {
            $table->dropColumn('observacion_devolucion');
        });
    }
};