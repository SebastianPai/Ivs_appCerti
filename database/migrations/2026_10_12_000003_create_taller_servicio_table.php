<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Asignación de servicios» de la plataforma anterior: cada taller solo puede pedir los tipos de
 * servicio que el admin le asignó. Los talleres que ya existen quedan con todos los activos, para
 * que nadie pierda acceso; los nuevos empiezan sin ninguno, como en el original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taller_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'service_type_id']);
        });

        $talleres = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'cliente')
            ->pluck('model_has_roles.model_id');
        $servicios = DB::table('service_types')->where('activo', true)->pluck('id');

        foreach ($talleres as $taller) {
            DB::table('taller_servicio')->insert($servicios->map(fn ($servicio) => [
                'user_id' => $taller, 'service_type_id' => $servicio, 'created_at' => now(), 'updated_at' => now(),
            ])->all());
        }

        if (! DB::table('system_settings')->where('key', 'servicios_por_taller')->exists()) {
            DB::table('system_settings')->insert(['key' => 'servicios_por_taller', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'servicios_por_taller')->delete();
        Schema::dropIfExists('taller_servicio');
    }
};
