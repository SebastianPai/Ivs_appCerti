<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contáctenos de la plataforma anterior: mensajes de los usuarios que el admin marca como gestionados. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_contacto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('email', 120);
            $table->string('asunto', 150);
            $table->text('mensaje');
            $table->timestamp('gestionado_en')->nullable();
            $table->foreignId('gestionado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('gestionado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_contacto');
    }
};
