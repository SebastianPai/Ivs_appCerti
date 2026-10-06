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
       Schema::create('solicitud_adjuntos', function (Blueprint $table) {
            $table->id();
            // Relación con la solicitud principal
            $table->foreignId('solicitud_id')->constrained('solicituds')->cascadeOnDelete();
            
            // Campos que definiste en tu Schema
            $table->string('nombre_adjunto'); // Ej: "Adjunto Preconversión *"
            $table->string('ruta_archivo')->nullable(); // El path de Firebase
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_adjuntos');
    }
};
