<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Pages\Admin\SystemSettings;
use App\Filament\Resources\Revisor\Pages\ViewSolicitudRevisor;
use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Filament\Widgets\DesempenoTalleres;
use App\Filament\Widgets\IndicadoresTiempos;
use App\Filament\Widgets\ProductividadEquipo;
use App\Filament\Widgets\SolicitudesPorDepartamento;
use App\Filament\Widgets\SolicitudesPorMes;
use App\Jobs\EnviarCorreo;
use App\Models\Actividad;
use App\Models\Chip;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\SystemSetting;
use App\Support\Archivo;
use App\Support\Correo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MejorasMercadoTest extends TestCase
{
    // ------------------------------------------------------------------
    // Archivos privados
    // ------------------------------------------------------------------

    public function test_los_documentos_se_sirven_solo_con_sesion_y_enlace_firmado(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('solicitudes/1/adjuntos/doc.pdf', '%PDF-1.4 prueba');
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn ($path, $exp) => \Illuminate\Support\Facades\URL::temporarySignedRoute('archivos.privado', $exp, ['path' => $path])
        );

        $url = Archivo::url('solicitudes/1/adjuntos/doc.pdf');
        $this->assertStringContainsString('/privado/solicitudes/1/adjuntos/doc.pdf', $url);
        $this->assertStringContainsString('signature=', $url);

        $this->get($url)->assertRedirect(); // sin sesión → login

        $this->actingAs($this->usuario('cliente'));
        $this->get($url)->assertOk();
        $this->get('/privado/solicitudes/1/adjuntos/doc.pdf')->assertForbidden(); // sin firma
        $this->get(str_replace('doc.pdf', 'otro.pdf', $url))->assertForbidden(); // firma de otro archivo

        $this->assertNull(Archivo::url('no/existe.pdf'));
    }

    public function test_el_comando_mueve_los_archivos_publicos_al_disco_privado(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('solicitudes/5/adjuntos/a.pdf', 'a');
        Storage::disk('public')->put('evaluadores/5/fotos/b.jpg', 'b');
        Storage::disk('public')->put('otra-cosa/c.txt', 'c');

        $this->artisan('ivs:archivos-privados', ['--simular' => true])->assertSuccessful();
        $this->assertTrue(Storage::disk('public')->exists('solicitudes/5/adjuntos/a.pdf'));

        $this->artisan('ivs:archivos-privados')->assertSuccessful();

        $this->assertFalse(Storage::disk('public')->exists('solicitudes/5/adjuntos/a.pdf'));
        $this->assertSame('a', Storage::disk('local')->get('solicitudes/5/adjuntos/a.pdf'));
        $this->assertSame('b', Storage::disk('local')->get('evaluadores/5/fotos/b.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('otra-cosa/c.txt'), 'Solo se mueven documentos de usuarios');
    }

    // ------------------------------------------------------------------
    // Correos y avisos
    // ------------------------------------------------------------------

    public function test_los_correos_van_a_la_cola_y_se_pueden_apagar_por_tipo(): void
    {
        Queue::fake();
        $taller = $this->usuario('cliente');

        Correo::enviar($taller, 'aprobacion', '🎉 Aprobado', 'emails.notificacion', ['titulo' => 'x', 'boton_url' => 'https://ivs.test/x']);
        Queue::assertPushed(EnviarCorreo::class, fn ($job) => $job->para === $taller->email);
        $this->assertSame(1, $taller->notifications()->count(), 'El aviso dentro de la app siempre llega');
        $this->assertSame('Aprobado', $taller->notifications()->first()->data['title']);

        SystemSetting::put('correos', true, ['desactivados' => ['aprobacion']]);
        Correo::enviar($taller, 'aprobacion', 'Otra', 'emails.notificacion', []);
        Correo::enviar($taller, 'devolucion', 'Devuelta', 'emails.notificacion', []);
        Queue::assertPushed(EnviarCorreo::class, 2);

        SystemSetting::put('correos', false, ['desactivados' => []]);
        Correo::enviar($taller, 'devolucion', 'Nada', 'emails.notificacion', []);
        Queue::assertPushed(EnviarCorreo::class, 2);
        $this->assertSame(4, $taller->notifications()->count());
    }

    public function test_la_configuracion_guarda_correos_vigencia_y_2fa(): void
    {
        $this->actingAs($this->usuario('admin'));

        Livewire::test(SystemSettings::class)
            ->assertSet('data.correos', true)
            ->assertSet('data.vigencia_meses', 12)
            ->set('data.correos_tipos', ['aprobacion'])
            ->set('data.vigencia_meses', 6)
            ->set('data.vigencia_dias_aviso', 15)
            ->set('data.exigir_2fa', true)
            ->set('data.exigir_2fa_roles', ['revisor'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(SystemSetting::correoActivo('aprobacion'));
        $this->assertFalse(SystemSetting::correoActivo('devolucion'));
        $this->assertSame(['meses' => 6, 'dias_aviso' => 15], SystemSetting::vigencia());
        $this->assertTrue(SystemSetting::exige2fa($this->usuario('revisor')));
        $this->assertFalse(SystemSetting::exige2fa($this->usuario('evaluador')));
    }

    // ------------------------------------------------------------------
    // 2FA
    // ------------------------------------------------------------------

    public function test_el_2fa_se_exige_solo_a_los_roles_configurados(): void
    {
        $admin = $this->usuario('admin');
        $taller = $this->usuario('cliente');

        $this->actingAs($admin)->get('/ivs')->assertOk();
        $this->actingAs($admin)->get('/ivs/profile')->assertOk()->assertSee('Autenticación');

        SystemSetting::put('exigir_2fa', true, ['roles' => ['admin']]);

        $this->actingAs($admin)->get('/ivs')->assertRedirectContains('multi-factor-authentication');
        $this->flushSession();
        $this->actingAs($taller)->get('/ivs')->assertOk();
        $this->flushSession();

        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', \DB::table('users')->where('id', $admin->id)->value('app_authentication_secret'), 'Se guarda cifrado');
        $this->actingAs($admin->fresh())->get('/ivs')->assertOk();
    }

    // ------------------------------------------------------------------
    // Auditoría
    // ------------------------------------------------------------------

    public function test_cada_cambio_de_estado_queda_en_el_historial(): void
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $solicitud = $this->solicitud($taller);

        $this->actingAs($evaluador);
        SolicitudVerificacion::create(['solicitud_id' => $solicitud->id, 'evaluador_id' => $evaluador->id, 'estado' => 'validada', 'datos_checklist' => ['a' => 1]]);
        $solicitud->update(['estado' => EstadoSolicitud::DevueltaTaller->value, 'observacion_devolucion' => 'Falta el acta de desmonte']);

        $cambio = Actividad::where('solicitud_id', $solicitud->id)->where('evento', 'estado')->sole();
        $this->assertSame($evaluador->id, $cambio->user_id);
        $this->assertSame('Pendiente de evaluación → Devuelta al taller. Motivo: Falta el acta de desmonte', $cambio->descripcion);
        $this->assertSame(['antes' => 'pendiente', 'despues' => 'devuelta_taller'], $cambio->cambios['estado']);

        // El taller ve la creación y los estados; no los detalles internos de la inspección
        $this->assertEqualsCanonicalizing(['creado', 'estado'], $solicitud->historialPara($taller)->pluck('evento')->unique()->all());
        $this->assertContains('inspeccion', $solicitud->historialPara($evaluador)->pluck('evento')->all());

        $this->actingAs($taller)->get("/ivs/solicituds/{$solicitud->id}")->assertOk()->assertSee('Falta el acta de desmonte');

        $this->flushSession();
        $this->actingAs($this->usuario('admin'))->get('/ivs/auditoria')->assertOk()->assertSee('Devuelta al taller');
        $this->flushSession();
        $this->actingAs($taller)->get('/ivs/auditoria')->assertForbidden();
    }

    public function test_se_registran_los_inicios_de_sesion_fallidos(): void
    {
        // El login de Filament dispara este evento de Laravel al fallar
        event(new \Illuminate\Auth\Events\Failed('web', null, ['email' => 'intruso@test.com', 'password' => 'clave-secreta']));

        $registro = Actividad::where('evento', 'login_fallido')->sole();
        $this->assertStringContainsString('intruso@test.com', $registro->descripcion);
        $this->assertStringNotContainsString('clave-secreta', json_encode($registro->toArray()), 'Nunca se guarda la contraseña');
    }

    // ------------------------------------------------------------------
    // Vigencias y renovación
    // ------------------------------------------------------------------

    public function test_al_aprobar_se_fija_la_vigencia_y_se_avisa_una_vez_antes_de_vencer(): void
    {
        Queue::fake();
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $revisor = $this->usuario('revisor');
        $solicitud = $this->solicitud($taller, ['estado' => EstadoSolicitud::Evaluada->value]);
        SolicitudVerificacion::create(['solicitud_id' => $solicitud->id, 'evaluador_id' => $evaluador->id, 'estado' => 'enviada', 'verificada_en' => now()]);

        $this->actingAs($revisor);
        Livewire::test(ViewSolicitudRevisor::class, ['record' => $solicitud->id])->callAction('aprobar');

        $solicitud->refresh();
        $this->assertSame(now()->addYear()->toDateString(), $solicitud->vence_el->toDateString());

        // Aún falta mucho: no se avisa
        $this->artisan('ivs:avisar-vencimientos')->assertSuccessful();
        $this->assertNull($solicitud->fresh()->aviso_vencimiento_at);

        $this->travelTo(now()->addYear()->subDays(10));
        $this->artisan('ivs:avisar-vencimientos')->assertSuccessful();
        $this->assertNotNull($solicitud->fresh()->aviso_vencimiento_at);
        Queue::assertPushed(EnviarCorreo::class, fn ($j) => $j->para === 'ana@test.com' && str_contains($j->asunto, 'por vencer'));
        Queue::assertPushed(EnviarCorreo::class, fn ($j) => $j->para === $taller->email && str_contains($j->asunto, 'por vencer'));
        $this->assertSame(1, Actividad::where('evento', 'aviso')->count());

        $this->artisan('ivs:avisar-vencimientos');
        $this->assertSame(1, Actividad::where('evento', 'aviso')->count(), 'No se repite el aviso');
    }

    public function test_por_renovar_excluye_los_vehiculos_que_ya_tienen_una_solicitud_nueva(): void
    {
        $taller = $this->usuario('cliente');
        $vieja = $this->solicitud($taller, ['vehicle_identification' => 'REN123', 'estado' => EstadoSolicitud::Aprobada->value, 'codigo' => 'IVS-1', 'vence_el' => now()->addDays(5)]);
        $otra = $this->solicitud($taller, ['vehicle_identification' => 'OTR456', 'estado' => EstadoSolicitud::Aprobada->value, 'codigo' => 'IVS-2', 'vence_el' => now()->addDays(200)]);

        $this->assertEquals([$vieja->id], Solicitud::porRenovar(30)->pluck('id')->all());

        // El taller inicia la renovación: el formulario llega con los datos copiados
        $this->actingAs($taller);
        Livewire::withQueryParams(['desde' => $vieja->id])
            ->test(CreateSolicitud::class)
            ->assertSet('data.vehicle_identification', 'REN123')
            ->assertSet('data.owner_nombre', 'Ana')
            ->assertSet('data.owner_email', 'ana@test.com');

        Solicitud::create([...$vieja->only(['user_id', 'vehicle_id', 'vehicle_identification_type_id', 'service_type_id']), 'vehicle_identification' => 'REN123']);
        $this->assertSame([], Solicitud::porRenovar(30)->pluck('id')->all());

        $this->get('/ivs/solicituds?activeTab=por_renovar')->assertOk();
        $this->assertNotNull($otra);
    }

    // ------------------------------------------------------------------
    // Tablero
    // ------------------------------------------------------------------

    public function test_el_tablero_muestra_indicadores_al_admin(): void
    {
        $taller = $this->usuario('cliente', ['name' => 'Taller Norte']);
        $evaluador = $this->usuario('evaluador', ['name' => 'Eva Luadora']);
        $this->usuario('revisor', ['name' => 'Rev Isor']);
        $s = $this->solicitud($taller, ['estado' => EstadoSolicitud::Aprobada->value, 'fecha_aprobacion' => now(), 'observacion_devolucion' => 'x']);
        SolicitudVerificacion::create(['solicitud_id' => $s->id, 'evaluador_id' => $evaluador->id, 'estado' => 'aprobada', 'verificada_en' => now()]);

        $this->actingAs($this->usuario('admin'));
        $this->get('/ivs')->assertOk()->assertSee('Periodo de los indicadores');

        Livewire::test(IndicadoresTiempos::class)->assertOk()->assertSee('Ciclo completo')->assertSee('100 %');
        Livewire::test(SolicitudesPorMes::class)->assertOk();
        Livewire::test(SolicitudesPorDepartamento::class)->assertOk();
        Livewire::test(ProductividadEquipo::class)->assertOk()->assertSee('Eva Luadora')->assertSee('Rev Isor');
        Livewire::test(DesempenoTalleres::class)->assertOk()->assertSee('Taller Norte');

        $this->flushSession();
        $this->actingAs($taller);
        $this->assertFalse(IndicadoresTiempos::canView());
        $this->get('/ivs')->assertOk()->assertDontSee('Periodo de los indicadores')->assertSee('Por renovar');
    }

    // ------------------------------------------------------------------
    // Inspección sin conexión
    // ------------------------------------------------------------------

    private function escenarioCampo(): array
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);

        return [$taller, $evaluador, $this->solicitud($taller)];
    }

    public function test_el_evaluador_descarga_sus_solicitudes_para_trabajar_sin_conexion(): void
    {
        [$taller, $evaluador, $solicitud] = $this->escenarioCampo();
        $ajena = $this->solicitud($this->usuario('cliente'));

        $this->actingAs($taller)->get('/campo')->assertForbidden();
        $this->actingAs($taller)->getJson('/campo/datos')->assertForbidden();

        $this->flushSession();
        $this->actingAs($evaluador)->get('/campo')->assertOk()->assertSee('Inspección sin conexión');
        $this->get('/campo-sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $datos = $this->getJson('/campo/datos')->assertOk()->json();
        $this->assertSame([$solicitud->id], array_column($datos['solicitudes'], 'id'), 'Solo las de sus talleres');
        $this->assertNotContains($ajena->id, array_column($datos['solicitudes'], 'id'));
        $this->assertSame(['CIL-1'], $datos['solicitudes'][0]['cilindros']);
        $this->assertNotEmpty($datos['secciones']);
        $this->assertCount(4, $datos['fotos_obligatorias']);
    }

    public function test_la_sincronizacion_guarda_todo_y_deja_listo_el_checklist(): void
    {
        Storage::fake('local');
        SystemSetting::put('chip_required', true);
        Chip::create(['codigo' => 'CHIP-9', 'activo' => true]);
        [, $evaluador, $solicitud] = $this->escenarioCampo();
        $this->actingAs($evaluador);

        $fotos = collect(\App\Filament\Resources\Evaluador\Solicituds\Pages\ViewSolicitud::FOTOS_OBLIGATORIAS)
            ->map(fn ($nombre) => ['nombre' => $nombre, 'archivo' => UploadedFile::fake()->image('f.jpg')])
            ->all();

        $base = [
            'declaracion' => '1',
            'lat' => 6.2442,
            'lng' => -75.5812,
            'precision' => 8.5,
            'capturado_en' => now()->subHours(3)->toIso8601String(),
            'chip_codigo' => ' chip-9 ',
            'observaciones' => 'Todo en orden',
            'checklist' => json_encode([
                'code_4_5' => 'c', 'code_4_5_valor' => 12, 'ntc_5_4_3' => 'nc', 'inventado' => 'c', 'doc_0957' => 'tal vez',
                'verificacion_cilindros' => [['numero_serie' => 'CIL-1', 'ultra_liviano' => 'no', 'ubicacion' => ['baul', 'luna']]],
            ]),
        ];

        // Sin GPS ni declaración no se acepta
        $this->postJson("/campo/sincronizar/{$solicitud->id}", ['lat' => 6])->assertUnprocessable()->assertJsonValidationErrors(['declaracion', 'lng', 'capturado_en']);

        $r = $this->post("/campo/sincronizar/{$solicitud->id}", [...$base, 'fotos' => $fotos], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true, 'listo_para_enviar' => true]);
        $this->assertStringEndsWith("/evaluacion/solicitudes/{$solicitud->id}/checklist", $r->json('siguiente'));

        $v = $solicitud->verificacion()->first();
        $this->assertSame('en_progreso', $v->estado);
        $this->assertTrue($v->conflicto_interes);
        $this->assertSame('CHIP-9', $v->chip->codigo);
        $this->assertNotNull($v->sincronizada_en);
        $this->assertCount(4, $v->fotos);
        Storage::disk('local')->assertExists($v->fotos->first()->ruta_foto);
        $this->assertEquals(['code_4_5' => 'c', 'code_4_5_valor' => 12, 'ntc_5_4_3' => 'nc', 'verificacion_cilindros' => [['numero_serie' => 'CIL-1', 'ultra_liviano' => 'no', 'ubicacion' => ['baul']]]], $v->datos_checklist, 'Solo entra lo que existe en el checklist oficial');
        $this->assertSame(now()->subHours(3)->format('Y-m-d H:i'), \Illuminate\Support\Carbon::parse(\App\Models\EvaluacionGeolocalizacion::first()->registrado_en)->format('Y-m-d H:i'), 'Se guarda la hora real de la captura en sitio');
        $this->assertDatabaseHas('actividades', ['solicitud_id' => $solicitud->id, 'evento' => 'sincronizacion']);

        // El checklist en línea abre con lo diligenciado sin conexión
        $this->get("/ivs/evaluacion/solicitudes/{$solicitud->id}/checklist")->assertOk();

        // Con un chip inválido y sin fotos: se guarda lo demás y se avisa
        $r = $this->postJson("/campo/sincronizar/{$solicitud->id}", [...$base, 'chip_codigo' => 'NO-EXISTE'])->assertOk();
        $this->assertStringContainsString('no está registrado', implode(' ', $r->json('avisos')));
    }

    public function test_no_se_sincroniza_una_solicitud_que_ya_no_esta_pendiente(): void
    {
        [, $evaluador, $solicitud] = $this->escenarioCampo();
        $solicitud->update(['estado' => EstadoSolicitud::Evaluada->value]);

        $this->actingAs($evaluador)->postJson("/campo/sincronizar/{$solicitud->id}", [
            'declaracion' => '1', 'lat' => 6, 'lng' => -75, 'capturado_en' => now()->toIso8601String(),
        ])->assertStatus(409)->assertJson(['ok' => false]);

        $otro = $this->usuario('evaluador');
        $this->flushSession();
        $this->actingAs($otro)->postJson("/campo/sincronizar/{$solicitud->id}", [])->assertNotFound();
    }
}
