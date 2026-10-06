<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_verificaciones', function (Blueprint $table) {
            $table->id();

            // 🔗 Relaciones principales
            $table->foreignId('solicitud_id')
                ->constrained('solicituds')
                ->cascadeOnDelete();

            $table->foreignId('evaluador_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 🔗 La columna id_chip y su llave foránea se crean en migraciones posteriores
            // (2025_12_17_184755 y 2026_10_05_000001). Aquí no existía aún y rompía `migrate:fresh`.

            // 📍 Georreferenciación
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable(); // metros

            // ⚖️ Declaraciones legales
            $table->boolean('conflicto_interes')->default(false);

            // 📊 Estado del proceso
            $table->enum('estado', [
                'iniciada',      // filtro iniciado
                'validada',      // pasó filtro
                'en_progreso',   // checklist
                'enviada',       // enviada a revisor
                'devuelta',      // observaciones
                'aprobada',
                'rechazada',
            ])->default('iniciada');

            //Metadatos de auditoría
            $table->timestamp('verificada_en')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // ❗ Regla clave
            $table->unique(['solicitud_id', 'evaluador_id']);

            //Datos del checklist en JSON
            $table->json('datos_checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_verificaciones');
    }
};
