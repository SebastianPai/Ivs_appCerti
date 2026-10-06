<?php

namespace Tests;

use App\Models\ApplicationType;
use App\Models\CombustionSystem;
use App\Models\CylinderBrand;
use App\Models\RegulatorBrand;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\Technology;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleIdentificationType;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel('admin');

        // Ninguna prueba debe salir a internet
        Http::preventStrayRequests();
    }

    protected function usuario(string $rol, array $attrs = []): User
    {
        $user = User::create([
            'name' => ucfirst($rol).' '.uniqid(),
            'email' => uniqid($rol).'@test.com',
            'password' => 'password',
            ...$attrs,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    /** Crea una solicitud completa (vehículo, regulador y cilindro) de un taller. */
    protected function solicitud(User $taller, array $attrs = []): Solicitud
    {
        $marca = VehicleBrand::firstOrCreate(['nombre' => 'MAZDA']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'nombre' => '3'], ['year_start' => 2010]);
        $placa = $attrs['vehicle_identification'] ?? 'ABC'.random_int(100, 999);

        $vehiculo = Vehicle::create([
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'vehicle_type_id' => VehicleType::firstOrCreate(['nombre' => 'AUTOMOVIL'])->id,
            'year' => 2015,
            'placa' => $placa,
            'fuel_base' => 'Gasolina',
        ]);

        $solicitud = Solicitud::create([
            'user_id' => $taller->id,
            'vehicle_id' => $vehiculo->id,
            'vehicle_identification_type_id' => VehicleIdentificationType::firstOrCreate(['nombre' => 'Placa'])->id,
            'vehicle_identification' => $placa,
            'service_type_id' => ServiceType::firstOrCreate(['nombre' => 'Conversión a GNV'], ['activo' => true])->id,
            'combustion_system_id' => CombustionSystem::firstOrCreate(['nombre' => 'GNV'])->id,
            'application_type_id' => ApplicationType::firstOrCreate(['nombre' => 'Dedicado'])->id,
            'technology_id' => Technology::firstOrCreate(['nombre' => '5ta generación'])->id,
            'owner_document_type' => 'CC',
            'owner_document' => '1020304050',
            'owner_nombre' => 'Ana',
            'owner_apellido' => 'Pérez',
            'owner_telefono' => '3001234567',
            'owner_email' => 'ana@test.com',
            'owner_departamento' => 'Antioquia',
            'owner_ciudad' => 'Medellín',
            'owner_direccion' => 'Calle 1 # 2-3',
            ...$attrs,
        ]);

        $solicitud->regulators()->create(['brand_id' => RegulatorBrand::firstOrCreate(['nombre' => 'TOMASETTO'])->id, 'numero_serie' => 'REG-1']);
        $solicitud->cilindros()->create([
            'brand_id' => CylinderBrand::firstOrCreate(['nombre' => 'FABER'])->id,
            'numero_serie' => 'CIL-1',
            'capacidad' => 60,
            'fecha_fabricacion' => '2020-01-01',
            'fecha_prueba' => '2024-01-01',
        ]);

        return $solicitud;
    }
}
