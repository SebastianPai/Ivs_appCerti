<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Datos que piden los exportables SICOM: código SICOM de las marcas, NIT del taller
 * y fecha de instalación del sistema de gas. También el rol que solo descarga exportables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['regulator_brands', 'cylinder_brands'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('codigo_sicom', 20)->nullable()->after('nombre');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('nit', 20)->nullable()->after('is_taller');
        });

        Schema::table('solicituds', function (Blueprint $table) {
            $table->date('fecha_instalacion')->nullable()->after('technology_id');
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'externo', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::where('name', 'externo')->delete();

        Schema::table('solicituds', function (Blueprint $table) {
            $table->dropColumn('fecha_instalacion');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nit');
        });

        foreach (['regulator_brands', 'cylinder_brands'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('codigo_sicom');
            });
        }
    }
};
