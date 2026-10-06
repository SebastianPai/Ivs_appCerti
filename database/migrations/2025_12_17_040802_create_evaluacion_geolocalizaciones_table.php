<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluacion_geolocalizaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('evaluador_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->decimal('precision_m', 8, 2)->nullable();

            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('registrado_en');

            $table->timestamps();

            $table->unique(['solicitud_id', 'evaluador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_geolocalizaciones');
    }
};
