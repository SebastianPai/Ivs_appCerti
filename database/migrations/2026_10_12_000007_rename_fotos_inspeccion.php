<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Las fotos de la inspección pasan a llamarse como en la plataforma anterior («Impronta», no «Importación»). */
return new class extends Migration
{
    private const NOMBRES = [
        'Importación de cilindros' => 'Fotos Impronta Cilindros',
        'Foto panorámica del vehículo' => 'Foto Panorámica',
        'Foto del regulador' => 'Foto Regulador',
        'Ubicación de cilindros' => 'Fotos Ubicación Cilindros',
    ];

    public function up(): void
    {
        foreach (self::NOMBRES as $anterior => $nuevo) {
            DB::table('verificacion_fotos')->where('nombre_foto', $anterior)->update(['nombre_foto' => $nuevo]);
        }
    }

    public function down(): void
    {
        foreach (self::NOMBRES as $anterior => $nuevo) {
            DB::table('verificacion_fotos')->where('nombre_foto', $nuevo)->update(['nombre_foto' => $anterior]);
        }
    }
};
