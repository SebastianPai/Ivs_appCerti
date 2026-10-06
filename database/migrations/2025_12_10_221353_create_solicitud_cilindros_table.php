<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cilindros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicituds')->cascadeOnDelete();

            $table->foreignId('brand_id')->nullable()->constrained('cylinder_brands')->nullOnDelete();
            $table->string('numero_serie')->nullable();
            $table->integer('capacidad')->nullable();
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_prueba')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cilindros');
    }
};
