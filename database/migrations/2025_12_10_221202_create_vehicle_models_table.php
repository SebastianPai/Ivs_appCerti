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
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')
                ->constrained('vehicle_brands')
                ->cascadeOnDelete();

            $table->string('nombre');              // COROLLA, SPARK, D-MAX
            $table->integer('year_start')->nullable(); // Año inicio fabricación
            $table->integer('year_end')->nullable();   // Año fin (NULL = vigente)

            $table->timestamps();

            $table->unique(['brand_id', 'nombre']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
