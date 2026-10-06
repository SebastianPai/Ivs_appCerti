<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\Pages\EvaluacionChecklist;
use App\Filament\Resources\Evaluador\Solicituds\Pages\VerificacionPrevia;
use App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud as EvaluadorView;
use App\Filament\Resources\Revisor\Pages\ViewSolicitudRevisor;
use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Models\Chip;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Models\Vehicle;
use App\Support\ChecklistTecnico;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FlujoCertificacionTest extends TestCase
{
    public function test_el_taller_crea_una_solicitud_y_reutiliza_el_vehiculo_existente(): void
    {
        $taller = $this->usuario('cliente');
        $base = $this->solicitud($taller, ['estado' => EstadoSolicitud::Aprobada->value, 'vehicle_identification' => 'XYZ987']);
        $this->actingAs($taller);

        $vehiculo = $base->vehicle;
        $deshacer = Repeater::fake(); // claves numéricas en los repeaters para poder llenarlos en la prueba

        Livewire::test(CreateSolicitud::class)
            ->fillForm([
                'vehicle_identification_type_id' => $base->vehicle_identification_type_id,
                'vehicle_identification' => 'xyz 987',
                'brand_id' => $vehiculo->brand_id,
                'model_id' => $vehiculo->model_id,
                'year' => 2016,
                'vehicle_type_id' => $vehiculo->vehicle_type_id,
                'fuel_base' => 'Gasolina',
                'service_type_id' => $base->service_type_id,
                'combustion_system_id' => $base->combustion_system_id,
                'application_type_id' => $base->application_type_id,
                'technology_id' => $base->technology_id,
                'owner_document_type' => 'CC',
                'owner_document' => '1020304050',
                'owner_nombre' => 'Ana',
                'owner_apellido' => 'Pérez',
                'owner_telefono' => '300 123 4567',
                'owner_email' => 'ana@test.com',
                'owner_departamento' => 'Antioquia',
                'owner_ciudad' => 'Medellín',
                'owner_direccion' => 'Calle 1 # 2-3',
                'regulators' => [['brand_id' => $base->regulators->first()->brand_id, 'numero_serie' => 'r-2']],
                'cilindros' => [[
                    'brand_id' => $base->cilindros->first()->brand_id,
                    'numero_serie' => 'c-2',
                    'capacidad' => 60,
                    'fecha_fabricacion' => '2021-01-01',
                    'fecha_prueba' => '2024-06-01',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $deshacer();
        $nueva = Solicitud::latest('id')->first();

        $this->assertSame('XYZ987', $nueva->vehicle_identification);
        $this->assertSame($vehiculo->id, $nueva->vehicle_id, 'Debe reutilizar el vehículo, no fallar por placa duplicada');
        $this->assertSame(1, Vehicle::where('placa', 'XYZ987')->count());
        $this->assertSame(2016, $vehiculo->fresh()->year);
        $this->assertSame('3001234567', $nueva->owner_telefono);
        $this->assertSame(EstadoSolicitud::Pendiente->value, $nueva->estado);
        $this->assertSame(['R-2'], $nueva->regulators->pluck('numero_serie')->all());
    }

    public function test_validaciones_del_formulario_del_taller(): void
    {
        $taller = $this->usuario('cliente');
        $abierta = $this->solicitud($taller, ['vehicle_identification' => 'DUP123']);
        $this->actingAs($taller);

        Livewire::test(CreateSolicitud::class)
            ->fillForm([
                'vehicle_identification_type_id' => $abierta->vehicle_identification_type_id,
                'vehicle_identification' => 'DUP123',
                'owner_document_type' => 'CC',
                'owner_document' => '12AB',
                'owner_telefono' => '12345',
                'owner_email' => 'no-es-correo',
            ])
            ->call('create')
            ->assertHasFormErrors(['vehicle_identification', 'owner_document', 'owner_telefono', 'owner_email']);

        Livewire::test(CreateSolicitud::class)
            ->fillForm(['vehicle_identification' => 'NOVALIDA'])
            ->call('create')
            ->assertHasFormErrors(['vehicle_identification']);
    }

    public function test_flujo_completo_evaluador_y_revisor_hasta_el_certificado(): void
    {
        Storage::fake('public');
        SystemSetting::put('chip_required', true);
        Chip::create(['codigo' => 'CHIP-001', 'activo' => true]);

        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $revisor = $this->usuario('revisor');
        $evaluador->clientesAsignados()->attach($taller);
        $evaluador->revisores()->attach($revisor);
        $solicitud = $this->solicitud($taller);

        // --- Paso 1: filtro de seguridad
        $this->actingAs($evaluador);

        Livewire::test(VerificacionPrevia::class, ['record' => $solicitud->id])
            ->call('continuar')
            ->assertHasFormErrors(['confirmo_conflicto']);

        Livewire::test(VerificacionPrevia::class, ['record' => $solicitud->id])
            ->call('guardarGeolocalizacion', ['lat' => 6.25, 'lng' => -75.56, 'accuracy' => 12])
            ->fillForm(['confirmo_conflicto' => true])
            ->call('continuar')
            ->assertRedirect("/ivs/evaluacion/solicitudes/{$solicitud->id}");

        $this->assertTrue($solicitud->verificacion()->first()->conflicto_interes);

        // --- Paso 2: chip y fotos
        $fotos = collect(range(0, 3))->map(fn () => UploadedFile::fake()->image('foto.jpg'))->all();

        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])
            ->fillForm(['chip_codigo' => 'NO-EXISTE', 'fotos' => $fotos])
            ->callAction('continuar')
            ->assertHasErrors(['data.chip_codigo']);

        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])
            ->fillForm(['chip_codigo' => ' chip-001 ', 'fotos' => $fotos])
            ->callAction('continuar')
            ->assertHasNoErrors()
            ->assertRedirect("/ivs/evaluacion/solicitudes/{$solicitud->id}/checklist");

        $verificacion = $solicitud->verificacion()->first();
        $this->assertSame('CHIP-001', $verificacion->chip->codigo);
        $this->assertCount(4, $verificacion->fotos);

        // --- Paso 3: checklist
        $respuestas = ['verificacion_cilindros' => [['numero_serie' => 'CIL-1', 'ultra_liviano' => 'no', 'ubicacion' => ['baul']]]];
        foreach (ChecklistTecnico::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                $respuestas[$item['id']] = 'c';
                if ($item['medicion'] ?? false) {
                    $respuestas[$item['id'].'_valor'] = 5; // menor al mínimo en los que tienen mínimo
                }
            }
        }

        $deshacer = Repeater::fake();
        $checklist = Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id]);

        // Cada cilindro declarado aparece con su número de serie (antes salía "N/A")
        $this->assertSame(['CIL-1'], collect($checklist->get('data.verificacion_cilindros'))->pluck('numero_serie')->values()->all());

        $checklist->callAction('guardarBorrador');

        $this->assertSame('en_progreso', $verificacion->fresh()->estado, 'Guardar progreso ya no falla por el estado "borrador"');

        $checklist->fillForm($respuestas)
            ->callAction('finalizar')
            ->assertHasErrors(['data.code_4_5_valor', 'data.code_5_2_7_valor']);

        $respuestas['code_4_5_valor'] = 15;
        $respuestas['code_5_2_7_valor'] = 25;

        Livewire::test(EvaluacionChecklist::class, ['record' => $solicitud->id])
            ->fillForm($respuestas)
            ->callAction('finalizar')
            ->assertHasNoErrors();
        $deshacer();

        $this->assertSame(EstadoSolicitud::Evaluada->value, $solicitud->fresh()->estado);

        // --- Revisor: devuelve y luego aprueba
        $this->actingAs($revisor);
        $this->get('/ivs/revisor/auditoria')->assertOk()->assertSee($solicitud->vehicle_identification);
        $this->get("/ivs/revisor/auditoria/{$solicitud->id}")->assertOk()->assertSee('CHIP-001');

        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->callAction('aprobar');

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::Aprobada->value, $solicitud->estado);
        $this->assertMatchesRegularExpression('/^IVS-\d{4}-\d{6}$/', $solicitud->codigo);
        $this->assertSame($revisor->id, $solicitud->revisor_id);

        $this->actingAs($taller)->get("/solicitud/{$solicitud->id}/certificado")->assertOk();
    }

    public function test_el_revisor_devuelve_al_evaluador_con_motivo(): void
    {
        $revisor = $this->usuario('revisor');
        $evaluador = $this->usuario('evaluador');
        $solicitud = $this->solicitud($this->usuario('cliente'), ['estado' => EstadoSolicitud::Evaluada->value]);
        $solicitud->verificaciones()->create(['evaluador_id' => $evaluador->id, 'estado' => 'enviada']);

        $this->actingAs($revisor);

        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])
            ->callAction('devolver', ['observaciones_revisor' => 'La foto del regulador está borrosa.']);

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::CorreccionTecnica->value, $solicitud->estado);
        $this->assertSame('La foto del regulador está borrosa.', $solicitud->observaciones_revisor);
        $this->assertTrue($solicitud->esEvaluable(), 'Vuelve a la bandeja del evaluador');
    }

    public function test_chip_no_puede_usarse_en_dos_vehiculos(): void
    {
        $chip = Chip::create(['codigo' => 'CHIP-777', 'activo' => true]);
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');

        $primera = $this->solicitud($taller, ['vehicle_identification' => 'AAA111']);
        $primera->verificaciones()->create(['evaluador_id' => $evaluador->id, 'id_chip' => $chip->id]);
        $segunda = $this->solicitud($taller, ['vehicle_identification' => 'BBB222']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Services\ChipValidationService::validar('CHIP-777', $segunda);
    }
}
