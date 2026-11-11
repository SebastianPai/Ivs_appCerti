<?php

use App\Support\CatalogoVehiculos;
use Illuminate\Database\Migrations\Migration;

/**
 * Carga las 142 marcas y 1.473 líneas de vehículo de la plataforma anterior. Va como migración
 * para que llegue a producción con el despliegue (allá no se corren seeders). Solo agrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        CatalogoVehiculos::importar();
    }

    public function down(): void
    {
        // No se deshace: las marcas y líneas pueden estar ya en uso por vehículos y solicitudes
    }
};
