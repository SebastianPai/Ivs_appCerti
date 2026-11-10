<?php

namespace Tests\Feature;

use App\Filament\Resources\Evaluador\Solicituds\Pages\EvaluacionChecklist;
use App\Models\Chip;
use App\Models\CombustionSystem;
use App\Models\CylinderBrand;
use App\Models\RegulatorBrand;
use App\Models\ServiceType;
use App\Models\SystemSetting;
use App\Models\Technology;
use App\Support\ChecklistTecnico;
use Database\Seeders\CylinderBrandSeeder;
use Database\Seeders\RegulatorBrandSeeder;
use Database\Seeders\ServiceTypeSeeder;
use Tests\TestCase;

class ChecklistYCatalogosTest extends TestCase
{
    public function test_el_checklist_tiene_los_61_items_de_la_norma_sin_repetir_identificadores(): void
    {
        $items = collect(ChecklistTecnico::secciones())->flatMap(fn ($s) => $s['items']);

        $this->assertCount(9, ChecklistTecnico::secciones());
        $this->assertCount(61, $items);
        $this->assertCount(61, $items->pluck('id')->unique());
        $this->assertSame(['code_4_5', 'code_5_2_7', 'code_4_1_2_5', 'res_29_1'], $items->where('medicion', true)->pluck('id')->values()->all());
    }

    public function test_el_prellenado_sugiere_segun_combustion_y_numero_de_cilindros(): void
    {
        $solicitud = $this->solicitud($this->usuario('cliente'), [
            'combustion_system_id' => CombustionSystem::firstOrCreate(['nombre' => 'INYECTADO'])->id,
            'technology_id' => Technology::firstOrCreate(['nombre' => 'QUINTA'])->id,
        ]);

        $sugerido = ChecklistTecnico::valoresIniciales($solicitud);

        $this->assertSame('c', $sugerido['code_4_5']);
        $this->assertSame('na', $sugerido['code_5_2_11'], 'línea dañada: lo habitual es que no aplique');
        $this->assertSame('na', $sugerido['ntc_5_3_10'], 'quinta generación no usa mezclador');
        $this->assertSame('na', $sugerido['ntc_5_4_4'], 'electroválvula de carburador en un inyectado');
        $this->assertSame('na', $sugerido['ntc_5_4_3'], 'un solo cilindro');
        $this->assertSame('na', $sugerido['doc_0957'], 'el taller no subió los anexos');
        $this->assertArrayNotHasKey('ntc_5_1_14', $sugerido);

        $solicitud->cilindros()->create(['numero_serie' => 'CIL-2', 'capacidad' => 40]);
        $this->assertSame('c', ChecklistTecnico::valoresIniciales($solicitud)['ntc_5_1_25']);

        // Los anexos se sugieren en «Cumple» solo si el taller los cargó
        $solicitud->adjuntos()->create(['nombre_adjunto' => 'Adjunto Preconversión *', 'ruta_archivo' => 'a.pdf']);
        $solicitud->adjuntos()->create(['nombre_adjunto' => 'Adjunto Acta desmonte *', 'ruta_archivo' => 'b.pdf']);
        $solicitud->adjuntos()->create(['nombre_adjunto' => 'Adjunto Prueba hidrostática *', 'ruta_archivo' => null]);
        $sugerido = ChecklistTecnico::valoresIniciales($solicitud);
        $this->assertSame('na', $sugerido['doc_0957'], 'falta la posconversión');
        $this->assertSame('c', $sugerido['doc_acta']);
        $this->assertSame('na', $sugerido['doc_ensayo'], 'casilla sin archivo');
    }

    public function test_la_ubicacion_y_el_tipo_de_cilindro_responden_solos_los_items_que_dependen_de_ellos(): void
    {
        $this->assertCount(31, ChecklistTecnico::REGLAS_UBICACION, 'todas las combinaciones de 5 ubicaciones');
        $ids = collect(ChecklistTecnico::secciones())->flatMap(fn ($s) => $s['items'])->pluck('id');
        $this->assertEmpty(array_diff([...ChecklistTecnico::ITEMS_UBICACION, ...ChecklistTecnico::ITEMS_ULTRALIVIANO, ...ChecklistTecnico::ITEMS_DEDICADO], $ids->all()));
        $deUbicacion = fn (array $r) => array_map(fn ($id) => $r[$id], ChecklistTecnico::ITEMS_UBICACION);

        // Maletero: solo aplican la cubierta hermética del compartimiento de carga y la ventilación
        $r = ChecklistTecnico::reglasDinamicas('no', ['baul'], false);
        $this->assertSame(['na', 'na', 'na', 'na', 'na', 'c', 'c'], $deUbicacion($r));
        $this->assertSame('na', $r['ntc_5_1_14']);
        $this->assertSame('na', $r['ntc_4_1_2_2_fibra']);
        $this->assertArrayNotHasKey('ntc_5_4_5', $r);

        // Exterior + maletero: tres ítems quedan sin regla y los contesta el evaluador
        $r = ChecklistTecnico::reglasDinamicas('si', ['exterior', 'baul', 'no-existe'], true);
        $this->assertSame(['c', null, null, null, 'na', 'c', 'c'], $deUbicacion($r));
        $this->assertSame('c', $r['ntc_5_1_14']);
        $this->assertSame('na', $r['ntc_5_4_5'], 'dedicado: no tiene gasolina');
        $this->assertSame('na', $r['ntc_5_6_1'], 'dedicado: no tiene selector');

        // Varias ubicaciones a la vez
        $r = ChecklistTecnico::reglasDinamicas('si', ['pasajeros', 'chasis_ejes'], false);
        $this->assertSame(['c', 'c', 'na', 'na', 'c', 'na', 'c'], $deUbicacion($r));
        $this->assertSame('c', $r['ntc_5_1_14']);

        // Sin nada marcado todavía no se toca ningún ítem
        $this->assertSame([], ChecklistTecnico::reglasDinamicas(null, [], false));
    }

    public function test_el_checklist_del_panel_aplica_las_reglas_al_marcar_los_cilindros(): void
    {
        $solicitud = $this->solicitud($this->usuario('cliente'), [
            'application_type_id' => \App\Models\ApplicationType::firstOrCreate(['nombre' => 'TRANSFORMADO DEDICADO'])->id,
        ]);

        $pagina = new EvaluacionChecklist;
        $pagina->record = $solicitud;
        $pagina->data = ['ntc_5_1_22' => 'c', 'ultra_liviano' => 'si', 'ubicacion_cilindro' => ['exterior', 'pasajeros']];
        $pagina->aplicarReglas();

        $this->assertSame('c', $pagina->data['ntc_5_1_21']);
        $this->assertNull($pagina->data['ntc_5_1_22'], 'sin regla: se borra la respuesta automática anterior');
        $this->assertSame('c', $pagina->data['ntc_5_1_18']);
        $this->assertSame('c', $pagina->data['ntc_5_1_14']);
        $this->assertSame('na', $pagina->data['ntc_5_6_1']);
    }

    public function test_cada_tipo_de_servicio_exige_lo_suyo(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $tipo = fn (string $nombre) => ServiceType::where('nombre', $nombre)->firstOrFail();

        $this->assertTrue($tipo('INICIAL')->chip_nuevo);
        $this->assertTrue($tipo('INICIAL')->fotos_obligatorias);
        $this->assertFalse($tipo('REVISION ANUAL')->chip_nuevo);
        $this->assertFalse($tipo('REVISION ANUAL')->fotos_obligatorias, 'las fotos solo se exigen en el inicial');
        $this->assertTrue($tipo('REVISION ANUAL + CAMBIO')->chip_nuevo);
        $this->assertTrue($tipo('REVISION ANUAL')->exige('cilindros'));
        $this->assertFalse($tipo('CAMBIO DE CHIP')->exige('cilindros'));
        $this->assertTrue($tipo('CAMBIO DE CHIP')->exige('conformidad'));
        $this->assertSame(['posconversion'], $tipo('GARANTIA DE CHIP')->requisitos);

        $documentos = \App\Filament\Resources\Solicituds\Schemas\AttachmentForm::class;
        $this->assertTrue($documentos::obligatorio('Adjunto Preconversión *', null), 'sin tipo se exige todo');
        $this->assertFalse($documentos::obligatorio('Adjunto Preconversión *', $tipo('GARANTIA DE CHIP')));
        $this->assertTrue($documentos::obligatorio('Adjunto Posconversión *', $tipo('GARANTIA DE CHIP')));
        $this->assertFalse($documentos::obligatorio('Adjunto Preconversión Opcional', $tipo('INICIAL')));
    }

    public function test_un_servicio_con_chip_nuevo_no_acepta_un_chip_ya_usado(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $chip = Chip::create(['codigo' => 'CHIP-888', 'activo' => true]);
        $taller = $this->usuario('cliente');

        // Mismo vehículo: la revisión anual sigue con su chip, el cambio de chip no
        $anterior = $this->solicitud($taller, ['vehicle_identification' => 'AAA111']);
        $anterior->verificaciones()->create(['evaluador_id' => $this->usuario('evaluador')->id, 'id_chip' => $chip->id]);

        $nueva = $anterior->replicate()->fill(['service_type_id' => ServiceType::where('nombre', 'REVISION ANUAL')->value('id')]);
        $nueva->save();
        $this->assertSame($chip->id, \App\Services\ChipValidationService::validar('chip-888', $nueva)->id);

        $nueva->update(['service_type_id' => ServiceType::where('nombre', 'CAMBIO DE CHIP')->value('id')]);
        $this->expectExceptionMessage('requiere un cambio de chip');
        \App\Services\ChipValidationService::validar('CHIP-888', $nueva->fresh());
    }

    public function test_el_evaluador_solo_ve_los_botones_que_corresponden_al_servicio(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);
        $this->actingAs($evaluador);
        $pagina = \App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud::class;

        $anual = $this->solicitud($taller, ['vehicle_identification' => 'AAA111']);
        $anual->update(['service_type_id' => ServiceType::where('nombre', 'REVISION ANUAL')->value('id')]);
        \App\Models\SolicitudVerificacion::create(['solicitud_id' => $anual->id, 'evaluador_id' => $evaluador->id, 'conflicto_interes' => true, 'lat' => 6.2, 'lng' => -75.5, 'estado' => 'validada']);
        \Livewire\Livewire::test($pagina, ['record' => $anual->id])
            ->assertSee('Guardar y continuar al checklist')
            ->assertDontSee('Aceptar Cambio de chip');

        $cambio = $this->solicitud($taller, ['vehicle_identification' => 'BBB222']);
        $cambio->update(['service_type_id' => ServiceType::where('nombre', 'CAMBIO DE CHIP')->value('id')]);
        \App\Models\SolicitudVerificacion::create(['solicitud_id' => $cambio->id, 'evaluador_id' => $evaluador->id, 'conflicto_interes' => true, 'lat' => 6.2, 'lng' => -75.5, 'estado' => 'validada']);
        \Livewire\Livewire::test($pagina, ['record' => $cambio->id])
            ->assertSee('Aceptar Cambio de chip')
            ->assertDontSee('Guardar y continuar al checklist');
    }

    public function test_con_la_consulta_a_sicom_apagada_se_acepta_el_chip_leido_y_el_certificado_puede_llevar_un_evaluador_fijo(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        $anual = $this->solicitud($this->usuario('cliente'), ['vehicle_identification' => 'AAA111']);
        $anual->update(['service_type_id' => ServiceType::where('nombre', 'REVISION ANUAL')->value('id')]);

        // Apagada (como está hoy en la plataforma anterior): no se llama a SICOM y el chip del vehículo vale
        $chip = \App\Services\ChipValidationService::validar('chip-del-carro', $anual->fresh());
        $this->assertSame(['CHIP-DEL-CARRO', 'Chip del vehículo, sin consulta a SICOM'], [$chip->codigo, $chip->descripcion]);
        \Illuminate\Support\Facades\Http::assertNothingSent();

        // «Nombre evaluador» fijo en el certificado
        $evaluador = $this->usuario('evaluador');
        $anual->verificaciones()->create(['evaluador_id' => $evaluador->id, 'id_chip' => $chip->id]);
        $this->assertSame($evaluador->name, \App\Support\MarcaCertificado::nombreEvaluador($anual->fresh()));

        \App\Models\SystemSetting::put('certificado_evaluador_fijo', true, ['nombre' => 'Evaluador Titular']);
        $this->assertSame('Evaluador Titular', \App\Support\MarcaCertificado::nombreEvaluador($anual->fresh()));
    }

    public function test_el_chip_se_valida_contra_sicom_segun_el_tipo_de_servicio(): void
    {
        \App\Models\SystemSetting::put('sicom_consulta_evaluacion', true);
        $this->seed(ServiceTypeSeeder::class);
        $validar = fn (string $codigo, $solicitud) => \App\Services\ChipValidationService::validar($codigo, $solicitud);
        $caido = false;
        $falla = function (string $mensaje, \Closure $accion) {
            try {
                $accion();
                $this->fail("Se esperaba: {$mensaje}");
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertStringContainsString($mensaje, collect($e->errors())->flatten()->implode(' '));
            }
        };
        \Illuminate\Support\Facades\Http::fake(['www.datos.gov.co/*' => function ($request) use (&$caido) { return $caido ? \Illuminate\Support\Facades\Http::response(null, 500) : \Illuminate\Support\Facades\Http::response(match ($request['idrom_chip']) {
            'CHIP-EN-SICOM' => [['estado' => 'ACTIVO', 'placa' => 'AAA111', 'vin' => 'SIN DEFINIR', 'idrom_chip' => 'CHIP-EN-SICOM']],
            'CHIP-DE-OTRO' => [['estado' => 'ACTIVO', 'placa' => 'ZZZ000', 'vin' => 'SIN DEFINIR', 'idrom_chip' => 'CHIP-DE-OTRO']],
            default => [],
        }); }]);

        $anual = $this->solicitud($this->usuario('cliente'), ['vehicle_identification' => 'AAA111']);
        $anual->update(['service_type_id' => ServiceType::where('nombre', 'REVISION ANUAL')->value('id')]);
        $anual = $anual->fresh();

        // Revisión anual: el chip no está en el inventario, pero SICOM lo conoce para esta placa
        $chip = $validar('chip-en-sicom', $anual);
        $this->assertSame('CHIP-EN-SICOM', $chip->codigo);
        $this->assertSame('Chip del vehículo, verificado en SICOM', $chip->descripcion);

        $falla('no existe en el SICOM', fn () => $validar('CHIP-FANTASMA', $anual));
        $falla('registrado en SICOM para el vehículo ZZZ000', fn () => $validar('CHIP-DE-OTRO', $anual));

        // Servicio con chip nuevo: sale del inventario y SICOM no debe conocerlo
        $inicial = $this->solicitud($this->usuario('cliente'), ['vehicle_identification' => 'BBB222']);
        $inicial->update(['service_type_id' => ServiceType::where('nombre', 'INICIAL')->value('id')]);
        $inicial = $inicial->fresh();
        Chip::create(['codigo' => 'CHIP-NUEVO', 'activo' => true]);

        $this->assertSame('CHIP-NUEVO', $validar('CHIP-NUEVO', $inicial)->codigo);
        $falla('requiere un cambio de chip', fn () => $validar('CHIP-EN-SICOM', $inicial));
        $falla('no está registrado en el sistema', fn () => $validar('CHIP-FANTASMA', $inicial));

        // SICOM caído: un chip desconocido no pasa, pero el que ya está en el sistema sí
        $caido = true;
        $falla('no fue posible verificarlo en SICOM', fn () => $validar('CHIP-SIN-RED', $anual));
        $this->assertSame('CHIP-EN-SICOM', $validar('CHIP-EN-SICOM', $anual)->codigo);
    }

    public function test_los_catalogos_traen_las_marcas_y_tipos_de_la_plataforma_anterior(): void
    {
        $this->seed([CylinderBrandSeeder::class, RegulatorBrandSeeder::class, ServiceTypeSeeder::class]);
        $this->seed(CylinderBrandSeeder::class); // repetir no duplica

        $this->assertSame(52, CylinderBrand::count());
        $this->assertSame(94, RegulatorBrand::count());
        $this->assertSame('12', CylinderBrand::where('nombre', 'FABER')->value('codigo_sicom'));
        $this->assertSame(2, RegulatorBrand::where('codigo_sicom', '413')->count());
        $this->assertSame(
            ['codigo_exportado' => '7', 'nombre_exportado' => 'CAMBIO DE CHIP', 'clase' => 'cambio_chip'],
            ServiceType::where('nombre', 'CAMBIO DE CHIP')->first()->only(['codigo_exportado', 'nombre_exportado', 'clase']),
        );
        $this->assertSame('REVISION ANUAL', ServiceType::where('nombre', 'REVISION ANUAL + CAMBIO')->first()->nombreExportado());
        $this->assertSame(5, ServiceType::where('activo', true)->count());
    }
}
