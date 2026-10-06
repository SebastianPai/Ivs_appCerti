<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Pages\Admin\SystemSettings;
use App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud as EvaluadorView;
use App\Filament\Resources\ServiceTypes\Pages\CreateServiceType;
use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Filament\Resources\Solicituds\Pages\EditSolicitud;
use App\Filament\Resources\Solicituds\Pages\ListSolicituds;
use App\Filament\Resources\Solicituds\Pages\ManageSolicitudAttachments;
use App\Filament\Resources\Solicituds\Schemas\AttachmentForm;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Recorre todas las pantallas y botones principales con cada rol. */
class RecorridoCompletoTest extends TestCase
{
    public function test_todas_las_pantallas_cargan_para_cada_rol(): void
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);
        $pendiente = $this->solicitud($taller);
        $aprobada = $this->solicitud($taller, ['estado' => EstadoSolicitud::Aprobada->value, 'codigo' => 'IVS-2026-000099', 'fecha_aprobacion' => now()]);
        $aprobada->verificaciones()->create(['evaluador_id' => $evaluador->id, 'estado' => 'aprobada']);

        $paginas = [
            'cliente' => [$taller, ['/ivs', '/ivs/solicituds', '/ivs/solicituds/create', "/ivs/solicituds/{$pendiente->id}", "/ivs/solicituds/{$pendiente->id}/edit", "/ivs/solicituds/{$pendiente->id}/attachments", "/ivs/solicituds/{$aprobada->id}", "/solicitud/{$aprobada->id}/certificado"]],
            'evaluador' => [$evaluador, ['/ivs', '/ivs/evaluacion/solicitudes', "/ivs/evaluacion/solicitudes/{$pendiente->id}/verificacion", "/ivs/evaluacion/solicitudes/{$aprobada->id}"]],
            'revisor' => [$this->usuario('revisor'), ['/ivs', '/ivs/revisor/auditoria', "/ivs/revisor/auditoria/{$aprobada->id}", "/solicitud/{$aprobada->id}/certificado"]],
        ];

        foreach ($paginas as $rol => [$usuario, $urls]) {
            foreach ($urls as $url) {
                $r = $this->actingAs($usuario)->get($url);
                $this->assertSame(200, $r->getStatusCode(), "$rol $url -> ".$r->headers->get('Location'));
                $this->app['auth']->forgetGuards();
                $this->flushSession();
            }
        }

        $admin = $this->usuario('admin');
        foreach (['/ivs', '/ivs/solicituds', '/ivs/solicituds/create', '/ivs/evaluacion/solicitudes', "/ivs/evaluacion/solicitudes/{$pendiente->id}",
            '/ivs/revisor/auditoria', "/ivs/revisor/auditoria/{$aprobada->id}", '/ivs/users', '/ivs/users/create', "/ivs/users/{$taller->id}",
            "/ivs/users/{$taller->id}/edit", '/ivs/service-types', '/ivs/service-types/create', '/ivs/configuracion-sistema',
            "/solicitud/{$aprobada->id}/certificado"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_el_admin_crea_una_solicitud_a_nombre_de_un_taller(): void
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $taller->evaluadores()->attach($evaluador);
        $base = $this->solicitud($taller, ['estado' => EstadoSolicitud::Aprobada->value]);
        $this->actingAs($this->usuario('admin'));

        $deshacer = Repeater::fake();
        Livewire::test(CreateSolicitud::class)
            ->assertFormFieldIsVisible('user_id')
            ->fillForm([
                'user_id' => $taller->id,
                'vehicle_identification_type_id' => $base->vehicle_identification_type_id,
                'vehicle_identification' => 'NUE321',
                'brand_id' => $base->vehicle->brand_id,
                'model_id' => $base->vehicle->model_id,
                'year' => 2018,
                'vehicle_type_id' => $base->vehicle->vehicle_type_id,
                'fuel_base' => 'Gasolina',
                'service_type_id' => $base->service_type_id,
                'combustion_system_id' => $base->combustion_system_id,
                'application_type_id' => $base->application_type_id,
                'technology_id' => $base->technology_id,
                'owner_document_type' => 'NIT',
                'owner_document' => '900123456-7',
                'owner_nombre' => 'Transportes SAS',
                'owner_telefono' => '6012345678',
                'owner_email' => 'flota@test.com',
                'owner_departamento' => 'Cundinamarca',
                'owner_ciudad' => 'Chía',
                'owner_direccion' => 'Km 5',
                'regulators' => [['brand_id' => $base->regulators->first()->brand_id, 'numero_serie' => 'R-9']],
                'cilindros' => [['brand_id' => $base->cilindros->first()->brand_id, 'numero_serie' => 'C-9', 'capacidad' => 80, 'fecha_fabricacion' => '2022-01-01', 'fecha_prueba' => '2023-01-01']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $deshacer();

        $nueva = Solicitud::where('vehicle_identification', 'NUE321')->firstOrFail();
        $this->assertSame($taller->id, $nueva->user_id, 'Queda a nombre del taller, no del admin');
    }

    public function test_el_taller_no_ve_el_campo_taller(): void
    {
        $this->actingAs($this->usuario('cliente'));
        Livewire::test(CreateSolicitud::class)->assertFormFieldIsHidden('user_id');
    }

    public function test_ciclo_devolucion_y_correccion_del_taller(): void
    {
        Storage::fake('public');
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);
        $solicitud = $this->solicitud($taller);
        SolicitudVerificacion::create(['solicitud_id' => $solicitud->id, 'evaluador_id' => $evaluador->id, 'conflicto_interes' => true, 'lat' => 6.2, 'lng' => -75.5, 'estado' => 'validada']);

        // El evaluador devuelve
        $this->actingAs($evaluador);
        Livewire::test(EvaluadorView::class, ['record' => $solicitud->id])
            ->callAction('devolverAlTaller', ['observacion' => 'Falta el acta de desmonte firmada.']);
        $this->assertSame(EstadoSolicitud::DevueltaTaller->value, $solicitud->fresh()->estado);

        // El taller ve la pestaña "Para corregir", edita y sube documentos
        $this->app['auth']->forgetGuards();
        $this->actingAs($taller);
        $this->get("/ivs/solicituds/{$solicitud->id}/attachments")->assertSee('Falta el acta de desmonte firmada.');

        Livewire::test(ListSolicituds::class)->assertSet('activeTab', 'requiere_accion')->assertCanSeeTableRecords([$solicitud]);

        Livewire::test(EditSolicitud::class, ['record' => $solicitud->id])
            ->fillForm(['owner_telefono' => '3109876543'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('3109876543', $solicitud->fresh()->owner_telefono);

        $archivos = [];
        foreach (array_values(AttachmentForm::DOCUMENTOS) as $i => $obligatorio) {
            $archivos[AttachmentForm::campo($i)] = $obligatorio ? UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf') : null;
        }

        Livewire::test(ManageSolicitudAttachments::class, ['record' => $solicitud->id])
            ->fillForm($archivos)
            ->call('save')
            ->assertHasNoFormErrors();

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::Subsanada->value, $solicitud->estado);
        $this->assertSame(5, $solicitud->adjuntos()->whereNotNull('ruta_archivo')->count());
        $this->assertTrue($solicitud->esEvaluable(), 'Vuelve a la bandeja del evaluador');

        // Una vez enviada a revisión el taller ya no puede editar
        $solicitud->update(['estado' => EstadoSolicitud::Evaluada->value]);
        $this->get("/ivs/solicituds/{$solicitud->id}/edit")->assertForbidden();
    }

    public function test_documentos_obligatorios_y_tipos_de_archivo(): void
    {
        Storage::fake('public');
        $taller = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller);
        $this->actingAs($taller);

        Livewire::test(ManageSolicitudAttachments::class, ['record' => $solicitud->id])
            ->call('save')
            ->assertHasFormErrors(['doc_0', 'doc_2']);

        Livewire::test(ManageSolicitudAttachments::class, ['record' => $solicitud->id])
            ->fillForm([
                'doc_0' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
                'doc_2' => [UploadedFile::fake()->create('enorme.pdf', 11000, 'application/pdf')],
            ])
            ->call('save')
            ->assertHasFormErrors(['doc_0', 'doc_2']);
    }

    public function test_gestion_de_usuarios_y_asignaciones(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Taller Nuevo', 'email' => 'nuevo@taller.com', 'role' => 'cliente',
                'password' => 'secreta123', 'department' => 'Antioquia', 'city' => 'Envigado',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = User::where('email', 'nuevo@taller.com')->firstOrFail();
        $this->assertTrue($nuevo->hasRole('cliente'));
        $this->assertSame('Envigado', $nuevo->city);

        // Editar precarga el rol y no exige contraseña
        Livewire::test(EditUser::class, ['record' => $nuevo->id])
            ->assertFormSet(['role' => 'cliente', 'city' => 'Envigado'])
            ->fillForm(['name' => 'Taller Renombrado'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Taller Renombrado', $nuevo->fresh()->name);

        // Correo duplicado
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'nuevo@taller.com', 'role' => 'cliente', 'password' => 'secreta123', 'department' => 'Antioquia', 'city' => 'Envigado'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        // Asignaciones
        $evaluador = $this->usuario('evaluador');
        $revisor = $this->usuario('revisor');

        Livewire::test(ListUsers::class)
            ->callTableAction('asignarEvaluadores', $nuevo, ['evaluadores' => [$evaluador->id]])
            ->callTableAction('asignarRevisores', $evaluador, ['revisores' => [$revisor->id]]);

        $this->assertTrue($nuevo->evaluadores()->whereKey($evaluador->id)->exists());
        $this->assertTrue($evaluador->revisores()->whereKey($revisor->id)->exists());

        // El admin no se puede quitar a sí mismo el rol
        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['role' => 'cliente', 'department' => 'Antioquia', 'city' => 'Medellín'])
            ->call('save');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_configuracion_y_restriccion_por_dispositivo(): void
    {
        $this->actingAs($this->usuario('admin'));

        Livewire::test(SystemSettings::class)
            ->fillForm([
                'chip_required' => true,
                'restrict_pc_access' => true,
                'acceso_cliente' => 'bloqueado',
                'acceso_evaluador' => 'solo_movil',
                'acceso_revisor' => 'todos',
            ])
            ->call('save');

        $this->assertTrue(SystemSetting::enabled('chip_required'));
        $this->assertTrue(SystemSetting::meta('restrict_pc_access', 'roles')['cliente']['block_all']);

        // Se vuelve a abrir y muestra lo guardado (antes siempre salía todo apagado)
        Livewire::test(SystemSettings::class)->assertFormSet(['chip_required' => true, 'acceso_evaluador' => 'solo_movil']);

        $this->app['auth']->forgetGuards();
        $pc = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'];
        $movil = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) Mobile'];

        $this->actingAs($this->usuario('cliente'))->get('/ivs', $pc)->assertRedirect('/acceso-restringido');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->usuario('evaluador'))->withServerVariables($pc)->get('/ivs')->assertRedirect('/acceso-restringido-pc');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->usuario('evaluador'))->withServerVariables($movil)->get('/ivs')->assertOk();
    }

    public function test_catalogo_tipos_de_servicio(): void
    {
        $this->actingAs($this->usuario('admin'));

        Livewire::test(CreateServiceType::class)
            ->fillForm(['nombre' => 'Revisión quinquenal', 'activo' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ServiceType::where('nombre', 'Revisión quinquenal')->exists());
    }

    public function test_el_certificado_trae_los_datos_reales(): void
    {
        $taller = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller, ['estado' => EstadoSolicitud::Aprobada->value, 'codigo' => 'IVS-2026-000123', 'fecha_aprobacion' => now()]);

        $html = view('pdf.certificado', ['record' => $solicitud->load(['cilindros.brand', 'regulators.brand', 'vehicle.brand'])])->render();

        $this->assertStringContainsString('IVS-2026-000123', $html);
        $this->assertStringContainsString('TOMASETTO', $html); // marca real del regulador de prueba
        $this->assertStringContainsString('REG-1', $html);
        $this->assertStringContainsString('CIL-1', $html);
        $this->assertStringContainsString('Medellín', $html);
        $this->assertStringNotContainsString('TO-12345', $html, 'Ya no inventa datos');
    }
}
