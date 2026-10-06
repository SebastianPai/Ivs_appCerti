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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_id')->constrained('vehicle_brands');
            $table->foreignId('model_id')->constrained('vehicle_models');
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types');

            $table->integer('year');

            // Identificación
            $table->string('vin')->nullable()->unique();
            $table->string('placa')->nullable()->unique();
            $table->string('motor')->nullable();

            // Datos técnicos base
            $table->enum('fuel_base', ['Gasolina', 'Diesel']);
            $table->decimal('engine_displacement', 5, 2)->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
