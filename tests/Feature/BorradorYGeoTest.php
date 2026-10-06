<?php

namespace Tests\Feature;

use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Services\ColombiaGeo;
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
