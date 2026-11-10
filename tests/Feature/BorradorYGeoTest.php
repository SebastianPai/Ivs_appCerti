<?php

namespace Tests\Feature;

use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Services\ColombiaGeo;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BorradorYGeoTest extends TestCase
{
    public function test_departamentos_y_ciudades_salen_del_archivo_local(): void
    {
        $this->assertCount(33, ColombiaGeo::departamentos());
        $this->assertArrayHasKey('Medellín', ColombiaGeo::ciudades('Antioquia'));
        $this->assertArrayHasKey('Bogotá D.C.', ColombiaGeo::ciudades('Bogotá'));
        $this->assertSame([], ColombiaGeo::ciudades('No existe'));
    }

    public function test_la_solicitud_a_medias_se_recupera_al_recargar(): void
    {
        $this->actingAs($taller = $this->usuario('cliente'));

        Livewire::test(CreateSolicitud::class)
            ->set('data.owner_nombre', 'Pedro')
            ->set('data.vehicle_identification', 'QWE456');

        // "Recargar la página" = montar el componente de nuevo
        Livewire::test(CreateSolicitud::class)
            ->assertSet('data.owner_nombre', 'Pedro')
            ->assertSet('data.vehicle_identification', 'QWE456')
            ->assertSet('hayBorrador', true)
            ->callAction('empezarDeCero');

        Livewire::test(CreateSolicitud::class)
            ->assertSet('data.owner_nombre', null)
            ->assertSet('hayBorrador', false);

        // El borrador es por usuario: otro taller no lo ve
        $this->actingAs($this->usuario('cliente'));
        Livewire::test(CreateSolicitud::class)->assertSet('hayBorrador', false);
    }

    public function test_al_digitar_la_placa_se_consulta_sicom_y_luego_los_datos_propios(): void
    {
        \App\Models\SystemSetting::put('sicom_consulta_evaluacion', true);
        $this->actingAs($taller = $this->usuario('cliente'));
        $anterior = $this->solicitud($taller, ['vehicle_identification' => 'LKL239']);

        Http::fake(['www.datos.gov.co/*' => fn ($request) => Http::response($request['placa'] === 'LKL239' ? [[
            'estado' => 'ACTIVO', 'placa' => 'LKL239', 'idrom_chip' => '59000001C01D4E01', 'fecha_instalacion' => '2024-08-22',
            'municipio_instalacion' => 'MEDELLIN', 'departamento_instalacion' => 'ANTIOQUIA',
        ]] : [])]);

        // Está en SICOM y en el sistema: trae la instalación de SICOM y el vehículo propio
        Livewire::test(CreateSolicitud::class)
            ->set('data.vehicle_identification', 'lkl-239')
            ->assertSet('data.fecha_instalacion', fn ($fecha) => str_starts_with((string) $fecha, '2024-08-22'))
            ->assertSet('data.brand_id', $anterior->vehicle->brand_id)
            // Del último servicio: sistema y equipos, pero no el propietario ni el tipo de servicio
            ->assertSet('data.technology_id', $anterior->technology_id)
            ->assertSet('data.regulators', fn ($equipos) => array_column($equipos, 'numero_serie') === ['REG-1'])
            ->assertSet('data.cilindros', fn ($equipos) => array_column($equipos, 'numero_serie') === ['CIL-1'])
            ->assertSet('data.owner_nombre', null)
            ->assertSet('data.service_type_id', null)
            ->assertNotified('Vehículo encontrado');

        // No está en ninguno de los dos: avisa y deja seguir
        Livewire::test(CreateSolicitud::class)
            ->set('data.vehicle_identification', 'ZZZ999')
            ->assertSet('data.fecha_instalacion', null)
            ->assertNotified('No se encontraron registros');

        // SICOM caído: no bloquea
        Http::fake(['www.datos.gov.co/*' => Http::response(null, 500)]);
        Livewire::test(CreateSolicitud::class)
            ->set('data.vehicle_identification', 'ZZZ998')
            ->assertSet('data.vehicle_identification', 'ZZZ998')
            ->assertSet('data.fecha_instalacion', null);
    }

    public function test_el_admin_ve_la_evaluacion_en_modo_consulta_con_mensaje_claro(): void
    {
        $taller = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller);

        $this->actingAs($this->usuario('admin'))
            ->get("/ivs/evaluacion/solicitudes/{$solicitud->id}")
            ->assertOk()
            ->assertSee('solo la puede hacer un usuario con rol evaluador');
    }
}
