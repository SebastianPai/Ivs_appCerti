<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Jobs\EnviarCorreo;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CorreosPlataformaAnteriorTest extends TestCase
{
    public function test_el_certificado_va_adjunto_al_taller_y_al_propietario_en_cada_envio(): void
    {
        Queue::fake();
        $taller = $this->usuario('cliente');
        $solicitud = $this->solicitud($taller);

        $solicitud->emitirCertificado();

        foreach ([$taller->email, 'ana@test.com'] as $destino) {
            Queue::assertPushed(EnviarCorreo::class, fn (EnviarCorreo $job) => $job->para === $destino
                && $job->vista === 'emails.certificado'
                && $job->certificadoDe === $solicitud->id
                && str_starts_with($job->asunto, 'Vehículo Certificado'));
        }

        // Reenvío tras una devolución: sale otra vez
        $solicitud->fresh()->emitirCertificado();
        Queue::assertPushed(EnviarCorreo::class, 4);

        // El trabajo arma el PDF y lo adjunta
        (new EnviarCorreo('ana@test.com', 'Vehículo Certificado', 'emails.certificado', ['placa' => 'ABC123', 'detalle' => 'x'], $solicitud->id))->handle();
        $mensaje = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame(['certificado.pdf'], array_map(fn ($adjunto) => $adjunto->getFilename(), $mensaje->getAttachments()));
        $this->assertStringContainsString('Adjunto puedes encontrar el certificado generado.', $mensaje->getHtmlBody());
    }

    public function test_el_taller_recibe_aviso_cuando_le_asignan_o_le_quitan_un_inspector(): void
    {
        Queue::fake();
        $this->actingAs($this->usuario('admin'));
        $taller = $this->usuario('cliente');
        $uno = $this->usuario('evaluador');
        $otro = $this->usuario('evaluador');

        Livewire::test(ListUsers::class)->callTableAction('asignarEvaluadores', $taller, ['evaluadores' => [$uno->id]]);
        Queue::assertPushed(EnviarCorreo::class, fn (EnviarCorreo $job) => $job->para === $taller->email
            && $job->asunto === 'Notificación Nuevo Inspector'
            && $job->datos['inspector'] === $uno->name && $job->datos['asignado'] === true);

        Livewire::test(ListUsers::class)->callTableAction('asignarEvaluadores', $taller, ['evaluadores' => [$otro->id]]);
        Queue::assertPushed(EnviarCorreo::class, fn (EnviarCorreo $job) => $job->datos['inspector'] === $uno->name && $job->datos['asignado'] === false);
        Queue::assertPushed(EnviarCorreo::class, 3);

        $html = view('emails.inspector', ['saludo' => $taller->name, 'inspector' => $uno->name, 'asignado' => false])->render();
        $this->assertStringContainsString('Inspector desvinculado:', $html);
        $this->assertSame($otro->id, $taller->evaluadores()->sole()->id);
    }
}
