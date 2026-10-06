<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    public function test_raiz_redirige_al_panel(): void
    {
        $this->get('/')->assertRedirect('/ivs');
    }

    public function test_un_taller_no_puede_entrar_a_modulos_ajenos_por_url(): void
    {
        $taller = $this->usuario('cliente');

        $this->actingAs($taller);

        $this->get('/ivs/users')->assertForbidden();
        $this->get('/ivs/service-types')->assertForbidden();
        $this->get('/ivs/configuracion-sistema')->assertForbidden();
        $this->get('/ivs/evaluacion/solicitudes')->assertForbidden();
        $this->get('/ivs/revisor/auditoria')->assertForbidden();
        $this->get('/ivs/solicituds')->assertOk();
    }

    public function test_el_admin_entra_a_todo(): void
    {
        $this->actingAs($this->usuario('admin'));

        foreach (['/ivs', '/ivs/users', '/ivs/solicituds', '/ivs/evaluacion/solicitudes', '/ivs/revisor/auditoria', '/ivs/configuracion-sistema'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_un_taller_no_ve_documentos_de_otro_taller(): void
    {
        $dueno = $this->usuario('cliente');
        $otro = $this->usuario('cliente');
        $solicitud = $this->solicitud($dueno);

        $this->actingAs($otro)->get("/ivs/solicituds/{$solicitud->id}/attachments")->assertNotFound();
        $this->actingAs($otro)->get("/ivs/solicituds/{$solicitud->id}")->assertNotFound();
    }

    public function test_el_dueno_si_ve_sus_documentos_y_su_solicitud(): void
    {
        $dueno = $this->usuario('cliente');
        $solicitud = $this->solicitud($dueno);

        $this->actingAs($dueno)->get("/ivs/solicituds/{$solicitud->id}/attachments")->assertOk()->assertSee('Documentos requeridos');
        $this->actingAs($dueno)->get("/ivs/solicituds/{$solicitud->id}")->assertOk()->assertSee($solicitud->vehicle_identification);
        $this->actingAs($dueno)->get('/ivs/solicituds/create')->assertOk();
        $this->actingAs($dueno)->get('/ivs')->assertOk()->assertSee('Para corregir');
    }

    public function test_paginas_del_evaluador_y_revisor_cargan(): void
    {
        $evaluador = $this->usuario('evaluador');
        $taller = $this->usuario('cliente');
        $evaluador->clientesAsignados()->attach($taller);
        $solicitud = $this->solicitud($taller);

        $this->actingAs($evaluador)->get("/ivs/evaluacion/solicitudes/{$solicitud->id}/verificacion")
            ->assertOk()
            ->assertSee('Filtro de seguridad obligatorio');
        $this->actingAs($evaluador)->get('/ivs')->assertOk()->assertSee('Por evaluar');
    }

    public function test_el_evaluador_solo_ve_talleres_asignados(): void
    {
        $evaluador = $this->usuario('evaluador');
        $asignado = $this->usuario('cliente');
        $ajeno = $this->usuario('cliente');
        $evaluador->clientesAsignados()->attach($asignado);

        $propia = $this->solicitud($asignado);
        $otra = $this->solicitud($ajeno);

        $this->actingAs($evaluador);

        $this->get('/ivs/evaluacion/solicitudes')->assertOk()->assertSee($propia->vehicle_identification)->assertDontSee($otra->vehicle_identification);
        $this->get("/ivs/evaluacion/solicitudes/{$otra->id}/verificacion")->assertNotFound();
        $this->get("/ivs/evaluacion/solicitudes/{$propia->id}/verificacion")->assertOk();
    }

    public function test_el_evaluador_no_puede_saltarse_el_filtro_de_seguridad(): void
    {
        $evaluador = $this->usuario('evaluador');
        $taller = $this->usuario('cliente');
        $evaluador->clientesAsignados()->attach($taller);
        $solicitud = $this->solicitud($taller);

        $this->actingAs($evaluador);

        $this->get("/ivs/evaluacion/solicitudes/{$solicitud->id}")
            ->assertRedirect("/ivs/evaluacion/solicitudes/{$solicitud->id}/verificacion");
        $this->get("/ivs/evaluacion/solicitudes/{$solicitud->id}/checklist")
            ->assertRedirect("/ivs/evaluacion/solicitudes/{$solicitud->id}");
    }

    public function test_certificado_protegido(): void
    {
        $taller = $this->usuario('cliente');
        $otro = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller, ['estado' => EstadoSolicitud::Evaluada->value]);
        $url = "/solicitud/{$solicitud->id}/certificado";

        $this->get($url)->assertRedirect('/ivs/login');
        $this->actingAs($otro)->get($url)->assertForbidden();
        // Evaluada pero no aprobada por el revisor: aún no hay certificado
        $this->actingAs($taller)->get($url)->assertForbidden();

        $solicitud->update(['estado' => EstadoSolicitud::Aprobada->value, 'codigo' => 'IVS-2026-000001', 'fecha_aprobacion' => now()]);

        $this->actingAs($taller)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_una_solicitud_aprobada_no_se_puede_borrar(): void
    {
        $solicitud = $this->solicitud($this->usuario('cliente'), ['estado' => EstadoSolicitud::Aprobada->value]);

        $this->assertFalse($solicitud->delete());
        $this->assertModelExists($solicitud);
    }

    public function test_no_se_borra_un_usuario_con_solicitudes(): void
    {
        $this->actingAs($admin = $this->usuario('admin'));
        $taller = $this->usuario('cliente');
        $this->solicitud($taller);

        $this->assertFalse(\App\Filament\Resources\Users\UserResource::canDelete($taller));
        $this->assertFalse(\App\Filament\Resources\Users\UserResource::canDelete($admin));
        $this->assertTrue(\App\Filament\Resources\Users\UserResource::canDelete($this->usuario('cliente')));
    }
}
