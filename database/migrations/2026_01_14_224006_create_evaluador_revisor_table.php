<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluador_revisor', function (Blueprint $table) {
            $table->id();

            // Quién es el evaluador
            $table->foreignId('evaluador_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Quién es el revisor asignado
            $table->foreignId('revisor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Evitar duplicados
            $table->unique(['evaluador_id', 'revisor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluador_revisor');
    }
};