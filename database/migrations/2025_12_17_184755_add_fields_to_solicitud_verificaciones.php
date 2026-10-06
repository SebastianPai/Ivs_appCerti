<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->string('id_chip')->nullable(); // Para ID del chip
            $table->text('observaciones')->nullable(); // Para observaciones del evaluador
            // Si necesitas más, e.g., para fotos, pero usa media library para eso
        });
    }

    public function down(): void {
        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->dropColumn(['id_chip', 'observaciones']);
        });
    }
};