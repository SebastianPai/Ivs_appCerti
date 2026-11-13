<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Support\Nube;
use App\Support\RespaldoBase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirebaseRespaldoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('firebase');
        config(['filesystems.disks.firebase.bucket' => 'ivs-pruebas.appspot.com', 'filesystems.disks.firebase.key_file_path' => '/ruta/llave.json']);
        $this->withoutDefer();
    }

    public function test_las_carpetas_en_firebase_van_por_solicitud(): void
    {
        $this->assertSame('solicitudes/15/documentos/a.pdf', Nube::rutaRemota('solicitudes/15/adjuntos/a.pdf'));
        $this->assertSame('solicitudes/15/fotos/b.jpg', Nube::rutaRemota('evaluadores/15/fotos/b.jpg'));
        $this->assertSame('talleres/camara-comercio/c.pdf', Nube::rutaRemota('camara-comercio/c.pdf'));
        $this->assertSame('configuracion/certificados-marca/d.png', Nube::rutaRemota('certificados-marca/d.png'));
    }

    public function test_cada_documento_y_foto_se_copia_y_se_recupera_desde_firebase(): void
    {
        $solicitud = $this->solicitud($this->usuario('cliente'));
        Storage::disk('local')->put("solicitudes/{$solicitud->id}/adjuntos/pre.pdf", '%PDF pre');
        $solicitud->adjuntos()->create(['nombre_adjunto' => 'Adjunto Preconversión *', 'ruta_archivo' => "solicitudes/{$solicitud->id}/adjuntos/pre.pdf"]);

        Storage::disk('firebase')->assertExists("solicitudes/{$solicitud->id}/documentos/pre.pdf");

        $verificacion = $solicitud->verificaciones()->create(['evaluador_id' => $this->usuario('evaluador')->id]);
        Storage::disk('local')->put("evaluadores/{$solicitud->id}/fotos/panoramica.jpg", 'jpg');
        $verificacion->fotos()->create(['nombre_foto' => 'Foto panorámica del vehículo', 'ruta_foto' => "evaluadores/{$solicitud->id}/fotos/panoramica.jpg"]);
        Storage::disk('firebase')->assertExists("solicitudes/{$solicitud->id}/fotos/panoramica.jpg");

        // Se perdió en el servidor: el enlace sale de Firebase
        Storage::disk('local')->delete("solicitudes/{$solicitud->id}/adjuntos/pre.pdf");
        $this->assertNotNull(\App\Support\Archivo::url("solicitudes/{$solicitud->id}/adjuntos/pre.pdf"));

        // Apagado en Configuración: no se copia
        SystemSetting::put('copia_firebase', false);
        Storage::disk('local')->put("solicitudes/{$solicitud->id}/adjuntos/pos.pdf", '%PDF pos');
        $solicitud->adjuntos()->create(['nombre_adjunto' => 'Adjunto Posconversión *', 'ruta_archivo' => "solicitudes/{$solicitud->id}/adjuntos/pos.pdf"]);
        Storage::disk('firebase')->assertMissing("solicitudes/{$solicitud->id}/documentos/pos.pdf");
    }

    public function test_copiar_pendientes_sube_lo_que_falta(): void
    {
        Storage::disk('local')->put('solicitudes/7/adjuntos/x.pdf', 'x');
        Storage::disk('local')->put('evaluadores/7/fotos/y.jpg', 'y');
        Storage::disk('local')->put('importaciones/temporal.json', '{}');

        $this->assertSame(['copiados' => 2, 'pendientes' => 0, 'errores' => 0], Nube::sincronizar());
        Storage::disk('firebase')->assertExists('solicitudes/7/fotos/y.jpg');
        Storage::disk('firebase')->assertMissing('otros/importaciones/temporal.json');
        $this->assertSame(0, Nube::sincronizar()['copiados'], 'lo ya copiado no se repite');
    }

    public function test_respaldo_de_la_base_en_firebase(): void
    {
        $this->usuario('admin', ['name' => 'Admin Respaldo']);

        $ruta = RespaldoBase::subir();

        $this->assertStringStartsWith('respaldos/base-de-datos/', $ruta);
        $sql = gzdecode(Storage::disk('firebase')->get($ruta));
        $this->assertStringContainsString('INSERT INTO `users`', $sql);
        $this->assertStringContainsString('Admin Respaldo', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
        $this->assertCount(1, RespaldoBase::lista());

        // Se conservan solo los últimos días
        foreach (range(1, RespaldoBase::DIAS + 3) as $i) {
            Storage::disk('firebase')->put(RespaldoBase::CARPETA.'/'.now()->subDays($i)->format('Y-m-d_His').'.sql.gz', 'x');
        }
        RespaldoBase::subir();
        $this->assertCount(RespaldoBase::DIAS, RespaldoBase::lista());
    }

    public function test_sin_credenciales_no_se_intenta_nada(): void
    {
        config(['filesystems.disks.firebase.bucket' => null]);

        $this->assertFalse(Nube::activa());
        Storage::disk('local')->put('solicitudes/9/adjuntos/z.pdf', 'z');
        $this->assertFalse(Nube::copiar('solicitudes/9/adjuntos/z.pdf'));
        $this->actingAs($this->usuario('admin'))->get('/ivs/configuracion-sistema')->assertOk()->assertSee('Firebase sin configurar');
    }
}
