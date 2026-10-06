<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chips', function (Blueprint $table) {
            $table->id();

            // Identificador físico / lógico del chip
            $table->string('codigo')->unique();

            // Para poder apagar un chip sin borrarlo
            $table->boolean('activo')->default(true);

            // Opcional: descripción
            $table->string('descripcion')->nullable();

            // Auditoría
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chips');
    }
};
