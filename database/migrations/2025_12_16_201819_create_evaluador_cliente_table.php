<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluador_cliente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('evaluador_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('cliente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['evaluador_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluador_cliente');
    }
};