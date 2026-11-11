<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Marcas y líneas de vehículo de la plataforma anterior (Administrador › Editar Vehículos),
 * copiadas en noviembre de 2026 a database/data/vehiculos.json. Allá había marcas repetidas
 * (DESOTO, DODGE, HIGER, HYUNDAI); aquí quedan unidas en una sola con todas sus líneas.
 *
 * Solo agrega: no borra ni cambia las marcas o líneas que ya existan, para no tocar los
 * vehículos y solicitudes que las usan. Si una marca ya existe con otras mayúsculas
 * («Chevrolet»), se deja con el nombre del original («CHEVROLET»).
 */
class CatalogoVehiculos
{
    public static function importar(): array
    {
        $catalogo = json_decode(file_get_contents(database_path('data/vehiculos.json')), true, flags: JSON_THROW_ON_ERROR);
        $ahora = now();
        $marcasNuevas = 0;
        $lineasNuevas = 0;

        DB::transaction(function () use ($catalogo, $ahora, &$marcasNuevas, &$lineasNuevas) {
            $marcas = DB::table('vehicle_brands')->get(['id', 'nombre'])
                ->keyBy(fn ($m) => mb_strtoupper(trim($m->nombre)));

            foreach ($catalogo as $nombre => $lineas) {
                $marca = $marcas->get(mb_strtoupper($nombre));

                if ($marca === null) {
                    $id = DB::table('vehicle_brands')->insertGetId([
                        'nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                    $marcasNuevas++;
                } else {
                    $id = $marca->id;
                    if ($marca->nombre !== $nombre) {
                        DB::table('vehicle_brands')->where('id', $id)->update(['nombre' => $nombre, 'updated_at' => $ahora]);
                    }
                }

                $existentes = DB::table('vehicle_models')->where('brand_id', $id)->pluck('nombre')
                    ->map(fn ($n) => mb_strtoupper(trim($n)))->flip();

                $faltan = collect($lineas)
                    ->reject(fn ($linea) => $existentes->has(mb_strtoupper($linea)))
                    ->map(fn ($linea) => ['brand_id' => $id, 'nombre' => $linea, 'created_at' => $ahora, 'updated_at' => $ahora])
                    ->values();

                foreach ($faltan->chunk(200) as $lote) {
                    DB::table('vehicle_models')->insert($lote->all());
                }
                $lineasNuevas += $faltan->count();

                Cache::forget("vehicle_models_brand_{$id}");
            }
        });

        Cache::forget('vehicle_brands');

        return ['marcas' => $marcasNuevas, 'lineas' => $lineasNuevas];
    }
}
