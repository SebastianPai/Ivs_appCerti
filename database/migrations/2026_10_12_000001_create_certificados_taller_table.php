<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado Taller de la plataforma anterior: un certificado mensual por taller con los
 * vehículos que se le certificaron en el mes. Se guarda quién lo generó y cuántos vehículos traía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_taller', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('generador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedInteger('cantidad');
            $table->timestamps();

            $table->index(['taller_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_taller');
    }
};
