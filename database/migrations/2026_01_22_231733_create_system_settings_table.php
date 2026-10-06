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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // Identificador lógico
            $table->string('key')->unique();

            // Valor principal (on/off)
            $table->boolean('enabled')->default(false);

            // Datos extra (roles, ids, etc)
            $table->json('meta')->nullable();

            // Auditoría
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
