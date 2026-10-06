<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicituds', function (Blueprint $table) {
            $table->id();

            // Usuario
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Servicio
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();

            // Vehículo certificado
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            // Identificación usada en la búsqueda
            $table->foreignId('vehicle_identification_type_id')
                ->nullable()
                ->constrained('identification_types')
                ->nullOnDelete();

            $table->string('vehicle_identification')->nullable();

            // Propietario
            $table->string('owner_document_type')->nullable();
            $table->string('owner_document')->nullable();
            $table->string('owner_nombre')->nullable();
            $table->string('owner_apellido')->nullable();
            $table->string('owner_direccion')->nullable();
            $table->string('owner_departamento')->nullable();
            $table->string('owner_ciudad')->nullable();
            $table->string('owner_telefono')->nullable();
            $table->string('owner_email')->nullable();

            // Sistema GNV / GLP
            $table->foreignId('combustion_system_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technology_id')->nullable()->constrained()->nullOnDelete();

            // Estado
            $table->string('estado')->default('pendiente');

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('solicituds');
    }
};
