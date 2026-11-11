<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\Pages\EvaluacionChecklist;
use App\Filament\Resources\Evaluador\Solicituds\Pages\ListSolicitudesAbiertas;
use App\Filament\Resources\Evaluador\Solicituds\Pages\VerificacionPrevia;
use App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud as EvaluadorView;
use App\Filament\Resources\Revisor\Pages\ViewSolicitudRevisor;
use App\Filament\Resources\Solicituds\Pages\ListSolicituds;
use App\Models\Chip;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ChecklistTecnico;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Comportamientos que los usuarios ya conocen de la plataforma anterior. */
class FlujoPlataformaAnteriorTest extends TestCase
{
    /** Taller, evaluador asignado y una solicitud con el filtro de seguridad ya pasado. */
    private function escenario(array $attrs = []): array
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(ServiceTypeSeeder::class);

        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);
        $solicitud = $this->solicitud($taller, $attrs);

        $this->actingAs($evaluador);
        Livewire::test(VerificacionPrevia::class, ['record' => $solicitud->id])
            ->call('guardarGeolocalizacion', ['lat' => 6.25, 'lng' => -75.56, 'accuracy' => 12])
            ->fillForm(['confirmo_conflicto' => true])
            ->call('continuar');

        return [$taller, $evaluador, $solicitud];
    }

    private function tipo(string $nombre): int
    {
        return ServiceType::where('nombre', $nombre)->value('id');
    }

    public function test_el_cambio_de_chip_se_acepta_sin_lista_y_se_aprueba_sin_certificado(): void
    {
        Chip::create(['codigo' => 'CHIP-NUEVO', 'activo' => true]);
        [$taller, , $solicitud] = $this->escenario();
        $solicitud->update(['service_type_id' => $this->tipo('CAMBIO DE CHIP')]);

        // Sin chip no se puede aceptar; no hay botón para seguir a la lista
        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])
            ->assertActionHidden('continuar')
            ->assertActionVisible('aceptarCambioChip')
            ->callAction('aceptarCambioChip')
            ->assertHasErrors(['data.chip_codigo']);

        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])
            ->fillForm(['chip_codigo' => 'chip-nuevo'])
            ->callAction('aceptarCambioChip')
            ->assertHasNoErrors();

        // Queda finalizada de una vez, sin número ni próxima revisión
        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::Aprobada->value, $solicitud->estado);
        $this->assertSame('CHIP-NUEVO', $solicitud->verificacion()->first()->chip->codigo);
        $this->assertNull($solicitud->codigo, 'el cambio de chip no genera certificado');
        $this->assertNull($solicitud->vence_el, 'ni próxima revisión');
        $this->assertFalse($solicitud->tieneCertificado());
        $this->assertTrue($solicitud->estaPendienteDeRevision());

        // El revisor la acepta sin tener que escribir observación
        $this->flushSession();
        $this->actingAs($this->usuario('revisor'));
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])->callAction('revisar')->assertHasNoActionErrors();
        $this->assertNotNull($solicitud->fresh()->revisada_en);

        $this->flushSession();
        $this->actingAs($taller)->get("/solicitud/{$solicitud->id}/certificado")->assertForbidden();

        // Con el modo apagado, espera la aprobación del revisor y tampoco genera certificado
        SystemSetting::put('certificado_al_evaluar', false);
        $otra = $solicitud->replicate()->fill(['estado' => EstadoSolicitud::Evaluada->value, 'revisada_en' => null, 'fecha_aprobacion' => null]);
        $otra->save();
        $otra->emitirCertificado($this->usuario('revisor')->id);
        $this->assertNotNull($otra->fresh()->revisada_en, 'aprobada por un revisor: ya queda revisada');
        $this->assertSame(EstadoSolicitud::Aprobada->value, $otra->fresh()->estado);
        $this->assertNull($otra->fresh()->codigo);
    }

    public function test_la_conclusion_no_cumple_rechaza_la_solicitud_con_su_motivo(): void
    {
        SystemSetting::put('chip_required', false);
        [, , $solicitud] = $this->escenario();

        $fotos = collect(range(0, 3))->mapWithKeys(fn ($i) => ["foto_{$i}" => UploadedFile::fake()->image('foto.jpg')])->all();
        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])->fillForm($fotos)->callAction('continuar')->assertHasNoErrors();

        $respuestas = ['ultra_liviano' => 'no', 'ubicacion_cilindro' => ['baul']];
        foreach (ChecklistTecnico::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                $respuestas[$item['id']] = 'c';
                if ($item['medicion'] ?? false) {
                    $respuestas[$item['id'].'_valor'] = 25;
                }
            }
        }

        // La conclusión y la observación son obligatorias
        Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id])
            ->fillForm($respuestas)
            ->callAction('finalizar')
            ->assertHasErrors(['data.conclusion', 'data.conclusion_observacion']);

        Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id])
            ->fillForm([...$respuestas, 'conclusion' => 'nc', 'conclusion_observacion' => 'Fuga en la válvula de llenado'])
            ->callAction('finalizar')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::Rechazada->value, $solicitud->estado);
        $this->assertSame('Fuga en la válvula de llenado', $solicitud->observacion_devolucion);
        $this->assertNull($solicitud->codigo);
        $this->assertFalse($solicitud->esEvaluable());
        $this->assertFalse($solicitud->esCancelable());
    }

    public function test_el_evaluador_y_el_taller_pueden_cancelar_una_solicitud_sin_certificado(): void
    {
        [$taller, , $solicitud] = $this->escenario();

        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])->callAction('cancelar');
        $this->assertSame(EstadoSolicitud::Cancelada->value, $solicitud->fresh()->estado);
        $this->assertDatabaseHas('actividades', ['solicitud_id' => $solicitud->id]);

        // El taller solo cancela lo que le devolvieron
        $pendiente = $this->solicitud($taller, ['vehicle_identification' => 'PEN111']);
        $devuelta = $this->solicitud($taller, ['vehicle_identification' => 'DEV222', 'estado' => EstadoSolicitud::DevueltaTaller->value]);
        $this->actingAs($taller);

        Livewire::test(ListSolicituds::class)
            ->set('activeTab', 'todas')
            ->assertTableActionHidden('cancelar', $pendiente)
            ->assertTableActionVisible('cancelar', $devuelta)
            ->callTableAction('cancelar', $devuelta);

        $this->assertSame(EstadoSolicitud::Cancelada->value, $devuelta->fresh()->estado);
        $this->assertSame(EstadoSolicitud::Pendiente->value, $pendiente->fresh()->estado);
    }

    public function test_el_evaluador_pasa_una_revision_anual_a_anual_mas_cambio(): void
    {
        [, , $solicitud] = $this->escenario();
        $inicial = $this->solicitud($solicitud->user, ['vehicle_identification' => 'INI333', 'service_type_id' => $this->tipo('INICIAL')]);
        $solicitud->update(['service_type_id' => $this->tipo('REVISION ANUAL')]);

        Livewire::test(ListSolicitudesAbiertas::class)
            ->assertTableActionHidden('cambiarAnualMasCambio', $inicial)
            ->callTableAction('cambiarAnualMasCambio', $solicitud);

        $this->assertSame('REVISION ANUAL + CAMBIO', $solicitud->fresh()->serviceType->nombre);
        $this->assertTrue($solicitud->fresh()->serviceType->chip_nuevo);
    }

    public function test_la_revision_anual_avisa_si_los_equipos_no_son_los_de_la_revision_anterior(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $taller = $this->usuario('cliente');
        $anterior = $this->solicitud($taller, ['vehicle_identification' => 'AAA111', 'estado' => EstadoSolicitud::Aprobada->value, 'fecha_aprobacion' => now()->subYear()]);

        $nueva = $anterior->replicate()->fill(['estado' => EstadoSolicitud::Pendiente->value, 'service_type_id' => $this->tipo('REVISION ANUAL')]);
        $nueva->save();
        $nueva->regulators()->create(['brand_id' => $anterior->regulators->first()->brand_id, 'numero_serie' => 'REG-1']);
        $nueva->cilindros()->create([...$anterior->cilindros->first()->only(['brand_id', 'numero_serie', 'capacidad', 'fecha_fabricacion']), 'fecha_prueba' => '2024-01-01']);

        $this->assertSame(['reguladores' => [], 'cilindros' => []], $nueva->alertasEquipos(), 'mismos equipos: sin avisos');

        $nueva->regulators()->first()->update(['numero_serie' => 'REG-OTRO']);
        $nueva->cilindros()->first()->update(['fecha_prueba' => '2025-06-01']);
        $alertas = $nueva->alertasEquipos();

        $this->assertStringContainsString('serial REG-OTRO y marca TOMASETTO', $alertas['reguladores'][0]);
        $this->assertStringContainsString('fecha de prueba hidrostática', $alertas['cilindros'][0]);

        // Solo aplica a revisiones anuales
        $nueva->update(['service_type_id' => $this->tipo('INICIAL')]);
        $this->assertSame([], $nueva->fresh()->alertasEquipos()['reguladores']);
    }

    public function test_el_certificado_sale_al_enviar_la_lista_y_el_revisor_solo_audita(): void
    {
        SystemSetting::put('certificado_al_evaluar', true);
        SystemSetting::put('chip_required', false);
        [$taller, , $solicitud] = $this->escenario();

        $fotos = collect(range(0, 3))->mapWithKeys(fn ($i) => ["foto_{$i}" => UploadedFile::fake()->image('foto.jpg')])->all();
        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])->fillForm($fotos)->callAction('continuar')->assertHasNoErrors();

        $respuestas = ['ultra_liviano' => 'no', 'ubicacion_cilindro' => ['baul'], 'conclusion' => 'c', 'conclusion_observacion' => 'Conforme'];
        foreach (ChecklistTecnico::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                $respuestas[$item['id']] = 'c';
                if ($item['medicion'] ?? false) {
                    $respuestas[$item['id'].'_valor'] = 25;
                }
            }
        }

        Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id])->fillForm($respuestas)->callAction('finalizar')->assertHasNoErrors();

        // El evaluador deja la solicitud certificada, sin esperar al revisor
        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::Aprobada->value, $solicitud->estado);
        $this->assertMatchesRegularExpression('/^\d+$/', $solicitud->codigo);
        $this->assertNotNull($solicitud->vence_el);
        $this->assertNull($solicitud->revisada_en);
        $this->assertTrue($solicitud->estaPendienteDeRevision());
        [$codigo, $expedicion] = [$solicitud->codigo, $solicitud->fecha_aprobacion];
        $this->flushSession();
        $this->actingAs($taller)->get("/solicitud/{$solicitud->id}/certificado")->assertOk();

        // El revisor la devuelve: conserva el número, y al reenviar no cambia
        $revisor = $this->usuario('revisor');
        $this->flushSession();
        $this->actingAs($revisor);
        $this->assertSame(1, Solicitud::query()->pendientesDeRevision()->count());
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->assertActionHidden('aprobar')
            ->callAction('devolver', ['observaciones_revisor' => 'Falta la foto del regulador en detalle.']);
        $this->assertSame(EstadoSolicitud::CorreccionTecnica->value, $solicitud->fresh()->estado);
        $this->assertSame($codigo, $solicitud->fresh()->codigo);

        $solicitud->fresh()->emitirCertificado();
        $this->assertSame($codigo, $solicitud->fresh()->codigo);
        $this->assertTrue($expedicion->equalTo($solicitud->fresh()->fecha_aprobacion), 'la fecha de expedición no cambia');

        // La observación al marcar revisada es opcional; la devolución sí exige el motivo
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->callAction('devolver', ['observaciones_revisor' => null])
            ->assertHasActionErrors(['observaciones_revisor']);
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->callAction('revisar', ['observaciones_revisor' => null])
            ->assertHasNoActionErrors();
        $this->assertNotNull($solicitud->fresh()->revisada_en);
        $solicitud->fresh()->update(['revisada_en' => null, 'revisor_id' => null]);
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->callAction('revisar', ['observaciones_revisor' => 'Todo en orden.'])
            ->assertHasNoActionErrors();
        $solicitud->refresh();
        $this->assertNotNull($solicitud->revisada_en);
        $this->assertSame($revisor->id, $solicitud->revisor_id);
        $this->assertSame(EstadoSolicitud::Aprobada->value, $solicitud->estado);

        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])->callAction('marcarNoRevisada');
        $this->assertNull($solicitud->fresh()->revisada_en);
    }

    public function test_pantallas_de_chips_vehiculos_y_diagnostico(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin);

        foreach (['/ivs/chips', '/ivs/vehiculos', '/ivs/diagnostico-chip'] as $ruta) {
            $this->get($ruta)->assertOk();
        }

        // Agregar Chips: 16 caracteres, siempre en mayúsculas y sin repetir
        Livewire::test(\App\Filament\Resources\Chips\Pages\ManageChips::class)
            ->callAction('create', ['codigo' => 'corto'])
            ->assertHasActionErrors(['codigo']);
        Livewire::test(\App\Filament\Resources\Chips\Pages\ManageChips::class)
            ->callAction('create', ['codigo' => '1a00000001bc2d03', 'activo' => true])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('chips', ['codigo' => '1A00000001BC2D03', 'activo' => true]);
        Livewire::test(\App\Filament\Resources\Chips\Pages\ManageChips::class)
            ->callAction('create', ['codigo' => '1A00000001BC2D03'])
            ->assertHasActionErrors(['codigo']);

        // Editar Vehículos: marca, modelo y tipo
        $pagina = Livewire::test(\App\Filament\Resources\VehicleModels\Pages\ManageVehicleModels::class);
        // Las marcas y líneas del original ya vienen cargadas; repetirlas, en cualquier mayúscula, no se deja
        $kia = \App\Models\VehicleBrand::where('nombre', 'KIA')->firstOrFail();
        $this->assertDatabaseHas('vehicle_models', ['brand_id' => $kia->id, 'nombre' => 'PICANTO']);
        Livewire::test(\App\Filament\Resources\VehicleModels\Pages\ManageVehicleModels::class)
            ->callAction('marca', ['nombre' => 'kia'])->assertHasActionErrors(['nombre']);
        Livewire::test(\App\Filament\Resources\VehicleModels\Pages\ManageVehicleModels::class)
            ->callAction('create', ['brand_id' => $kia->id, 'nombre' => 'picanto'])->assertHasActionErrors(['nombre']);

        $pagina->callAction('marca', ['nombre' => 'marca nueva'])->assertHasNoActionErrors();
        $pagina->callAction('tipo', ['nombre' => 'tipo nuevo'])->assertHasNoActionErrors();
        $marca = \App\Models\VehicleBrand::where('nombre', 'MARCA NUEVA')->firstOrFail();
        $pagina->callAction('create', ['brand_id' => $marca->id, 'nombre' => 'linea uno'])->assertHasNoActionErrors();
        $this->assertDatabaseHas('vehicle_models', ['brand_id' => $marca->id, 'nombre' => 'LINEA UNO']);
        $this->assertDatabaseHas('vehicle_types', ['nombre' => 'TIPO NUEVO']);
        $this->assertArrayHasKey($marca->id, \App\Models\VehicleBrand::cached()->all(), 'la lista de marcas se refresca');

        // Diagnóstico Chip: lo que hay en el sistema y en los datos abiertos de MinEnergía
        \Illuminate\Support\Facades\Http::fake(['www.datos.gov.co/*' => \Illuminate\Support\Facades\Http::response([
            ['estado' => 'ACTIVO', 'placa' => 'ABC123', 'vin' => 'SIN DEFINIR', 'fecha_instalacion' => '2024-08-22', 'municipio_instalacion' => 'MEDELLIN', 'departamento_instalacion' => 'ANTIOQUIA'],
        ])]);
        $resultado = Livewire::test(\App\Filament\Pages\DiagnosticoChip::class)
            ->fillForm(['chip_codigo' => '1a00000001bc2d03'])
            ->call('consultar')
            ->assertHasNoFormErrors()
            ->get('resultado');
        $this->assertSame('Activo', $resultado['En este sistema']['Estado']);
        $this->assertSame('Sin solicitud', $resultado['En este sistema']['Placa']);
        $this->assertSame('ABC123', $resultado['Datos abiertos de MinEnergía']['Placa']);
        $this->assertSame('22/08/2024', $resultado['Datos abiertos de MinEnergía']['Fecha de instalación']);

        // El taller no entra a ninguna
        $this->flushSession();
        $this->actingAs($this->usuario('cliente'));
        foreach (['/ivs/chips', '/ivs/vehiculos', '/ivs/diagnostico-chip'] as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    public function test_con_la_lista_prellenada_se_ocultan_los_items_ya_respondidos(): void
    {
        SystemSetting::put('checklist_prellenado', true);
        SystemSetting::put('chip_required', false);
        [, , $solicitud] = $this->escenario();
        $fotos = collect(range(0, 3))->mapWithKeys(fn ($i) => ["foto_{$i}" => UploadedFile::fake()->image('foto.jpg')])->all();
        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])->fillForm($fotos)->callAction('continuar');

        $pagina = Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id]);
        $oculto = fn (string $id, mixed $valor) => (new \ReflectionMethod(EvaluacionChecklist::class, 'estaOculto'))
            ->invoke($pagina->instance(), collect(ChecklistTecnico::secciones())->flatMap(fn ($s) => $s['items'])->firstWhere('id', $id), $valor);

        $this->assertTrue($oculto('ntc_5_2_4', 'c'), 'arranca en cumple: oculto');
        $this->assertFalse($oculto('ntc_5_2_4', 'nc'), 'un no cumple siempre se ve');
        $this->assertFalse($oculto('ntc_5_2_4', null), 'sin responder siempre se ve');
        $this->assertFalse($oculto('code_4_5', 'c'), 'pide centímetros: siempre visible');
        $this->assertFalse($oculto('code_5_2_11', 'na'), 'arranca en N/A: siempre visible');
        $this->assertFalse($oculto('ntc_5_1_21', 'c'), 'depende de la ubicación: siempre visible');
        $this->assertTrue($oculto('ntc_5_1_22', 'c'), '5.1.22 se oculta cuando la ubicación lo responde');

        $pagina->callAction('alternarItems');
        $this->assertFalse($oculto('ntc_5_2_4', 'c'), 'mostrar todos');

        SystemSetting::put('checklist_prellenado', false);
        $pagina->set('mostrarTodos', false)->assertActionHidden('alternarItems');
        $this->assertFalse($oculto('ntc_5_2_4', 'c'), 'sin prellenado no se oculta nada');
    }
}
