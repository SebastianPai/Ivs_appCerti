<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que cada tipo de servicio exige, como en la plataforma anterior: si el chip debe ser nuevo,
 * si las fotos del evaluador son obligatorias y qué documentos y equipos debe traer el taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->boolean('chip_nuevo')->default(false)->after('nombre_exportado');
            $table->boolean('fotos_obligatorias')->default(true)->after('chip_nuevo');
            // Lista de requisitos exigidos (ver ServiceType::REQUISITOS); null = todos
            $table->json('requisitos')->nullable()->after('fotos_obligatorias');
        });

        // Tipos que ya existen, con los valores que tenían en la plataforma anterior
        $documentos = ['preconversion', 'posconversion', 'conformidad', 'acta_desmonte', 'prueba_hidrostatica'];

        DB::table('service_types')
            ->whereIn('nombre', ['INICIAL', 'CAMBIO DE CHIP', 'REVISION ANUAL + CAMBIO', 'REVISION ANUAL + CAMBIO + BONO 300', 'REVISION ANUAL + CAMBIO + BONO 390', 'GARANTIA DE CHIP'])
            ->update(['chip_nuevo' => true]);
        DB::table('service_types')->whereNotIn('nombre', ['INICIAL', 'INICIAL GAZEL'])->whereNotNull('codigo_exportado')->update(['fotos_obligatorias' => false]);
        DB::table('service_types')->where('nombre', 'CAMBIO DE CHIP')->update(['requisitos' => json_encode($documentos)]);
        DB::table('service_types')->where('nombre', 'GARANTIA DE CHIP')->update(['requisitos' => json_encode(['posconversion'])]);
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn(['chip_nuevo', 'fotos_obligatorias', 'requisitos']);
        });
    }
};
