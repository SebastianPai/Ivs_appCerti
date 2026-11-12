<?php

namespace Tests\Feature;

use App\Jobs\EnviarCorreo;
use App\Models\Chip;
use App\Models\SystemSetting;
use App\Services\ChipValidationService;
use App\Support\Correo;
use App\Support\TextosCorreo;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Antispam y textos editables de los correos, chips inactivos, estado del chip y cabeceras de seguridad. */
class CorreosYChipTest extends TestCase
{
    public function test_un_destinatario_no_recibe_mas_correos_del_mismo_tipo_que_el_limite_por_hora(): void
    {
        Queue::fake();
        SystemSetting::put('correos', true, ['desactivados' => [], 'limite_hora' => 3]);
        $taller = $this->usuario('cliente');

        foreach (range(1, 10) as $i) {
            Correo::enviar($taller, 'solicitud_creada', "Solicitud iniciada - Placa: ABC{$i}", 'emails.notificacion', ['placa' => "ABC{$i}"]);
        }

        // Solo salen 3 correos, pero los 10 avisos quedan en la campanita
        Queue::assertPushed(EnviarCorreo::class, 3);
        $this->assertSame(10, $taller->notifications()->count());

        // Otro tipo tiene su propio conteo y el certificado nunca se limita
        Correo::enviar($taller, 'documentos', 'Documentos', 'emails.notificacion', ['placa' => 'X']);
        foreach (range(1, 5) as $i) {
            Correo::enviar('dueno@test.com', 'aprobacion', 'Certificado', 'emails.certificado', ['placa' => "C{$i}"]);
        }
        Queue::assertPushed(EnviarCorreo::class, 3 + 1 + 5);
    }

    public function test_el_admin_puede_cambiar_asunto_mensaje_y_pie_de_los_correos(): void
    {
        Queue::fake();
        SystemSetting::put('correos', true, [
            'desactivados' => [],
            'limite_hora' => 0,
            'pie' => 'Firma de prueba',
            'textos' => ['devolucion' => ['asunto' => 'Corrija la placa {placa}', 'mensaje' => 'Hola {nombre}, revise la placa {placa}.']],
        ]);

        Correo::enviar('taller@test.com', 'devolucion', 'Asunto de siempre', 'emails.solicitud_devuelta', [
            'user_name' => 'Taller Uno', 'placa' => 'ABC123', 'observacion' => 'x', 'url_gestion' => 'http://x',
        ]);

        Queue::assertPushed(EnviarCorreo::class, function (EnviarCorreo $job) {
            $html = view($job->vista, $job->datos)->render();

            return $job->asunto === 'Corrija la placa ABC123'
                && str_contains($html, 'Hola Taller Uno, revise la placa ABC123.')
                && str_contains($html, 'Firma de prueba');
        });

        // Lo que no se edita conserva el texto de siempre
        $this->assertSame('Asunto de siempre', TextosCorreo::asunto('documentos', 'Asunto de siempre'));
    }

    public function test_los_correos_de_la_cuenta_usan_la_plantilla_de_ivs(): void
    {
        $usuario = $this->usuario('cliente', ['name' => 'Taller Uno']);

        $bienvenida = app(\Filament\Auth\Notifications\VerifyEmail::class);
        $bienvenida->url = 'http://x/confirmar';
        $correo = $bienvenida->toMail($usuario);

        $this->assertSame(TextosCorreo::CUENTA['cuenta_bienvenida']['asunto'], $correo->subject);
        $this->assertStringContainsString('Le damos la bienvenida', (string) $correo->render());

        $aviso = app(\Filament\Auth\Notifications\NoticeOfEmailChangeRequest::class, ['newEmail' => 'nuevo@test.com', 'blockVerificationUrl' => 'http://x/bloquear']);
        $html = (string) $aviso->toMail($usuario)->render();
        $this->assertStringContainsString('nuevo@test.com', $html);
        $this->assertStringContainsString('http://x/bloquear', $html);
    }

    public function test_los_chips_inactivos_solo_pasan_si_la_configuracion_lo_permite(): void
    {
        $taller = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller);
        $solicitud->serviceType->update(['chip_nuevo' => true]);
        Chip::create(['codigo' => 'ABCDEF0123456789', 'activo' => false]);

        try {
            ChipValidationService::validar('ABCDEF0123456789', $solicitud->fresh());
            $this->fail('Un chip inactivo no debía pasar con la opción apagada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('está inactivo', collect($e->errors())->flatten()->first());
        }

        SystemSetting::put('chip_permitir_inactivo', true);
        $this->assertSame('ABCDEF0123456789', ChipValidationService::validar('abcdef0123456789', $solicitud->fresh())?->codigo);
    }

    public function test_el_lector_consulta_el_estado_del_chip_solo_para_evaluador_y_admin(): void
    {
        Chip::create(['codigo' => 'ABCDEF0123456789', 'activo' => false]);

        $this->actingAs($this->usuario('evaluador'))
            ->getJson('/chip/estado/abcdef0123456789')
            ->assertOk()
            ->assertJson(['codigo' => 'ABCDEF0123456789', 'registrado' => true, 'activo' => false, 'permite_inactivos' => false]);

        $this->getJson('/chip/estado/NOEXISTE00000000')->assertOk()->assertJson(['registrado' => false]);

        $this->actingAs($this->usuario('cliente'))->getJson('/chip/estado/ABCDEF0123456789')->assertForbidden();
    }

    public function test_las_paginas_llevan_cabeceras_de_seguridad(): void
    {
        $this->get('/ivs/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_solo_el_admin_ve_la_vista_previa_de_los_correos(): void
    {
        $this->actingAs($this->usuario('admin'))->get('/correos/vista-previa')->assertOk()->assertSee('Vista previa de los correos');
        $this->get('/correos/vista-previa?tipo=cuenta_password')->assertOk()->assertSee('Restablecer contraseña');
        $this->get('/correos/vista-previa?tipo=devolucion')->assertOk()->assertSee('Solicitud Devuelta');

        $this->actingAs($this->usuario('cliente'))->get('/correos/vista-previa')->assertForbidden();
    }

    public function test_importa_los_chips_de_la_plataforma_anterior_sin_duplicar_ni_tocar_los_existentes(): void
    {
        Chip::create(['codigo' => 'AAAA000000000001', 'activo' => false]);

        $resumen = \App\Support\ImportarChips::desdeTexto(json_encode(['result' => [
            ['idChip' => 'aaaa000000000001', 'estado' => 'ACTIVO'],
            ['idChip' => 'BBBB000000000002', 'estado' => 'ACTIVO'],
            ['idChip' => 'CCCC000000000003', 'estado' => 'INACTIVO'],
            ['idChip' => 'x', 'estado' => 1],
        ]]));

        $this->assertSame(2, $resumen['creados']);
        $this->assertSame(1, $resumen['existentes']);
        $this->assertCount(1, $resumen['omitidos']);
        $this->assertFalse(Chip::where('codigo', 'AAAA000000000001')->value('activo'));
        $this->assertTrue(Chip::where('codigo', 'BBBB000000000002')->value('activo'));
        $this->assertFalse(Chip::where('codigo', 'CCCC000000000003')->value('activo'));

        // Una lista simple (un código por línea) también sirve, y repetir no duplica
        $otra = \App\Support\ImportarChips::desdeTexto("DDDD000000000004\nBBBB000000000002\n");
        $this->assertSame(['creados' => 1, 'existentes' => 1, 'omitidos' => []], $otra);
        $this->assertSame(4, Chip::count());
    }

    public function test_los_avisos_de_una_solicitud_tomada_solo_le_llegan_a_su_evaluador(): void
    {
        $taller = $this->usuario('cliente');
        $encargado = $this->usuario('evaluador');
        $otro = $this->usuario('evaluador');
        $exEvaluador = $this->usuario('cliente'); // quedó asignado pero ya no tiene el rol
        $inactivo = $this->usuario('evaluador', ['activo' => false]);
        $taller->evaluadores()->attach([$encargado->id, $otro->id, $exEvaluador->id, $inactivo->id]);
        $solicitud = $this->solicitud($taller);

        // Nadie la ha tomado: a los evaluadores del taller que siguen activos y con el rol
        $this->assertEqualsCanonicalizing([$encargado->id, $otro->id], $solicitud->evaluadoresANotificar()->pluck('id')->all());

        // Ya la tomó uno: solo a él
        $solicitud->verificaciones()->create(['evaluador_id' => $encargado->id]);
        $this->assertSame([$encargado->id], $solicitud->fresh()->evaluadoresANotificar()->pluck('id')->all());
    }

    public function test_un_usuario_desactivado_no_recibe_correos_ni_avisos(): void
    {
        Queue::fake();
        $inactivo = $this->usuario('cliente', ['activo' => false]);

        Correo::enviar($inactivo, 'documentos', 'Documentos', 'emails.notificacion', ['placa' => 'ABC123']);

        Queue::assertNothingPushed();
        $this->assertSame(0, $inactivo->notifications()->count());
    }

    public function test_envia_un_correo_de_prueba_de_cada_tipo(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('ivs:correos-prueba', ['email' => 'pruebas@test.com'])->assertSuccessful();

        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $total = count(\App\Http\Controllers\VistaPreviaCorreosController::catalogo());
        $this->assertCount($total, $enviados);
        $primero = $enviados->first()->getOriginalMessage();
        $this->assertSame('[Prueba IVS 01] '.TextosCorreo::CUENTA['cuenta_bienvenida']['nombre'], $primero->getSubject());
        $this->assertStringContainsString('Correo de prueba 1 de '.$total, $primero->getHtmlBody());
    }
}
