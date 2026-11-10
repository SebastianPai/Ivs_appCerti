<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Pages\Admin\SystemSettings;
use App\Models\CertificadoTaller;
use App\Models\Solicitud;
use App\Support\MarcaCertificado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DisenoYVerificacionCertificadoTest extends TestCase
{
    private function certificada(array $attrs = []): Solicitud
    {
        $solicitud = $this->solicitud($this->usuario('cliente'), ['vehicle_identification' => 'QRS123']);
        $solicitud->update([
            'estado' => EstadoSolicitud::Aprobada->value,
            'codigo' => '161300',
            'fecha_aprobacion' => now(),
            'vence_el' => now()->addYear(),
            ...$attrs,
        ]);

        return $solicitud->fresh();
    }

    public function test_el_qr_del_certificado_lleva_a_la_pagina_publica_de_verificacion(): void
    {
        $solicitud = $this->certificada();
        $url = $solicitud->urlVerificacion();

        $this->assertStringContainsString('/verificar/161300?f=', $url);
        $this->assertStringNotContainsString('wa.me', $url);

        // El PDF trae el QR y sigue cabiendo en una página
        $html = view('pdf.certificado', ['record' => $solicitud])->render();
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Escanee para verificar este certificado', $html);
        $this->assertSame(1, preg_match_all('#/Type\s*/Page\b#', $solicitud->pdfCertificado()->output()));

        // Sin sesión: muestra el certificado, nada del propietario
        $this->get($url)
            ->assertOk()
            ->assertSee('Certificado válido y vigente')
            ->assertSee('161300')
            ->assertSee('QRS123')
            ->assertDontSee('Pérez')
            ->assertDontSee('1020304050');
    }

    public function test_la_verificacion_no_deja_recorrer_numeros_y_avisa_si_esta_vencido(): void
    {
        $solicitud = $this->certificada(['vence_el' => now()->subDay()]);

        $this->get($solicitud->urlVerificacion())->assertOk()->assertSee('Certificado vencido');

        // Sin la firma del enlace, o con la de otro número, no se muestra nada
        $this->get('/verificar/161300')->assertNotFound()->assertSee('Certificado no encontrado')->assertDontSee('QRS123');
        $this->get('/verificar/161300?f='.Solicitud::firmaVerificacion('161301'))->assertNotFound();
        $this->get('/verificar/999999?f='.Solicitud::firmaVerificacion('999999'))->assertNotFound();
    }

    public function test_el_admin_cambia_logos_firma_firmante_y_marca_de_agua(): void
    {
        Storage::fake('local');
        $this->actingAs($this->usuario('admin'));

        // Por defecto: el logo de la app, el firmante del original y la marca de agua tenue
        $this->assertStringEndsWith('logo-pdf.png', MarcaCertificado::imagen('logo'));
        $this->assertSame('JERSON GONGORA', MarcaCertificado::firmante());
        $this->assertSame(0.05, MarcaCertificado::opacidad());

        Livewire::test(SystemSettings::class)
            ->fillForm([
                'cert_firmante' => 'María Nueva Firma',
                'cert_cargo' => 'Directora técnica',
                'cert_opacidad' => 4,
                'cert_firma' => UploadedFile::fake()->image('firma.png', 300, 90),
                'cert_marca_agua' => UploadedFile::fake()->image('fondo.png', 400, 400),
                'cert_onac' => UploadedFile::fake()->image('onac.png', 120, 120),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('María Nueva Firma', MarcaCertificado::firmante());
        $this->assertSame(0.04, MarcaCertificado::opacidad());
        $this->assertStringContainsString('certificados-marca', MarcaCertificado::imagen('firma'));
        $this->assertStringEndsWith('logo-pdf.png', MarcaCertificado::imagen('logo'), 'lo que no se cargó sigue con el de la app');

        // Los dos PDF usan lo cargado
        $taller = $this->usuario('cliente');
        $certificado = CertificadoTaller::create(['taller_id' => $taller->id, 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30', 'cantidad' => 0]);
        $html = view('pdf.certificado-taller', ['certificado' => $certificado, 'vehiculos' => collect()])->render();

        $this->assertStringContainsString('MARÍA NUEVA FIRMA', $html);
        $this->assertStringContainsString('DIRECTORA TÉCNICA', $html);
        $this->assertStringContainsString('opacity: 0.04', $html);
        $this->assertSame(3, substr_count($html, 'certificados-marca'), 'firma, marca de agua y ONAC');
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b#', $certificado->pdf()->output()));

        $this->assertStringContainsString('opacity: 0.04', view('pdf.certificado', ['record' => $this->certificada()])->render());

        // Volver a guardar sin tocar las imágenes no las borra
        Livewire::test(SystemSettings::class)->fillForm(['cert_cargo' => 'Coordinadora'])->call('save')->assertHasNoFormErrors();
        $this->assertStringContainsString('certificados-marca', MarcaCertificado::imagen('firma'));
        $this->assertSame('Coordinadora', MarcaCertificado::cargo());
    }
}
