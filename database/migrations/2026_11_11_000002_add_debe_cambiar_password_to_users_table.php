<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Usuarios con contraseña inicial (los traídos de la plataforma anterior): la cambian al entrar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('password');
            // La plataforma anterior guardaba la dirección de cada usuario
            $table->string('direccion')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['debe_cambiar_password', 'direccion']);
        });
    }
};
