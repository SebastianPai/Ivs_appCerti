<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Inventario» de la plataforma anterior (Administrar Usuarios, primer ícono de la fila): los
 * equipos que la certificadora entrega a cada usuario. Dos teléfonos móviles y el flexómetro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            foreach (['1', '2'] as $n) {
                $table->boolean("tiene_movil{$n}")->default(false);
                $table->string("movil{$n}_marca")->nullable();
                $table->string("movil{$n}_modelo")->nullable();
                $table->string("movil{$n}_serial")->nullable();
                $table->boolean("movil{$n}_tiene_cargador")->default(false);
                $table->boolean("movil{$n}_tiene_caja")->default(false);
            }

            $table->string('flexometro_serial')->nullable();
            $table->date('flexometro_fecha_entrega')->nullable();
            $table->date('flexometro_fecha_verificacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_usuarios');
    }
};
