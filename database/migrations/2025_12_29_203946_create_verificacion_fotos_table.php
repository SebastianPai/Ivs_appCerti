<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('verificacion_fotos', function (Blueprint $table) {
            $table->id();
            // Relación con la verificación (no con la solicitud directa para auditoría)
            $table->foreignId('solicitud_verificacion_id')
                  ->constrained('solicitud_verificaciones')
                  ->cascadeOnDelete();
            
            $table->string('nombre_foto'); // Ej: "Foto Panorámica *"
            $table->string('ruta_foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('verificacion_fotos');
    }
};