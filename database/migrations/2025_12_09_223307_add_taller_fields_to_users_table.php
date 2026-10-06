<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_taller')->default(0);
            $table->string('camara_comercio')->nullable();
            $table->date('fecha_radicado')->nullable();
            $table->date('fecha_vencimiento')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_taller',
                'camara_comercio',
                'fecha_radicado',
                'fecha_vencimiento',
            ]);
        });
    }
};
