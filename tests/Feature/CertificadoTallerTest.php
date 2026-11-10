<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Filament\Pages\CertificadoTaller as Pagina;
use App\Models\Actividad;
use App\Models\CertificadoTaller;
use App\Models\Solicitud;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CertificadoTallerTest extends TestCase
{
    private const SEPTIEMBRE = ['desde' => '2026-09-01', 'hasta' => '2026-09-30'];

    /** Solicitud del taller con certificado expedido en la fecha dada. */
    private function certificada(User $taller, string $placa, string $expedicion, array $attrs = []): Solicitud
    {
        $solicitud = $this->solicitud($taller, ['vehicle_identification' => $placa]);
        $solicitud->update([
            'estado' => EstadoSolicitud::Aprobada->value,
            'codigo' => 'IVS-2026-'.$placa,
            'fecha_aprobacion' => $expedicion,
            ...$attrs,
        ]);

        return $solicitud;
    }

    public function test_cuenta_lo_que_tiene_numero_en_el_mes_revisado_o_no_y_devuelto_incluido(): void
    {
        $taller = $this->usuario('cliente');

        $this->certificada($taller, 'AAA111', '2026-09-01 00:00:05');
        $this->certificada($taller, 'BBB222', '2026-09-30 23:59:00', ['revisada_en' => '2026-10-02 10:00:00']);
        $this->certificada($taller, 'CCC333', '2026-09-15 10:00:00', ['estado' => EstadoSolicitud::CorreccionTecnica->value]);

        // Fuera: cambio de chip (sin número), otro mes y otro taller
        $this->certificada($taller, 'DDD444', '2026-09-10 10:00:00', ['codigo' => null]);
        $this->certificada($taller, 'EEE555', '2026-10-01 00:00:00');
        $this->certificada($this->usuario('cliente'), 'FFF666', '2026-09-10 10:00:00');

        $placas = CertificadoTaller::vehiculos($taller->id, '2026-09-01', '2026-09-30')->get()->map->placa()->all();

        $this->assertSame(['AAA111', 'CCC333', 'BBB222'], $placas);
    }

    public function test_solo_admite_un_mes_completo_y_un_taller(): void
    {
        $this->actingAs($this->usuario('admin'));
        $taller = $this->usuario('cliente');

        $this->get(Pagina::getUrl())->assertOk();

        Livewire::test(Pagina::class)
            ->fillForm(self::SEPTIEMBRE)
            ->call('verVehiculos')
            ->assertNotified('Seleccione las dos fechas y el taller')
            ->fillForm(['desde' => '2026-09-02', 'hasta' => '2026-09-30', 'taller_id' => $taller->id])
            ->call('verVehiculos')
            ->assertNotified('Solo se permite el primer día del mes en la fecha inicial')
            ->fillForm(['desde' => '2026-09-01', 'hasta' => '2026-09-29'])
            ->call('verVehiculos')
            ->assertNotified('Solo se permite el último día del mes en la fecha final')
            ->fillForm(['desde' => '2026-08-01', 'hasta' => '2026-09-30'])
            ->call('verVehiculos')
            ->assertNotified('Las dos fechas deben ser del mismo mes')
            ->assertSet('vista', 'generados');
    }

    public function test_genera_descarga_y_elimina_dejando_rastro(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin);
        $taller = $this->usuario('cliente');

        Livewire::test(Pagina::class)
            ->fillForm([...self::SEPTIEMBRE, 'taller_id' => $taller->id])
            ->call('verVehiculos')
            ->call('generar')
            ->assertNotified('No hay vehículos certificados para generar el certificado')
            ->assertNoFileDownloaded();

        $this->assertSame(0, CertificadoTaller::count());

        $this->certificada($taller, 'AAA111', '2026-09-05 08:00:00');
        $this->certificada($taller, 'BBB222', '2026-09-20 08:00:00');

        $pagina = Livewire::test(Pagina::class)
            ->fillForm([...self::SEPTIEMBRE, 'taller_id' => $taller->id])
            ->call('verVehiculos')
            ->assertSee('IVS-2026-AAA111')
            ->set('buscar', 'bbb')
            ->assertDontSee('IVS-2026-AAA111')
            ->assertSee('IVS-2026-BBB222')
            ->call('generar')
            ->assertFileDownloaded('certificadoTaller.pdf');

        $certificado = CertificadoTaller::sole();
        $this->assertSame([$taller->id, $admin->id, 2, '2026-09-01', '2026-09-30'], [
            $certificado->taller_id,
            $certificado->generador_id,
            $certificado->cantidad,
            $certificado->fecha_inicio->toDateString(),
            $certificado->fecha_fin->toDateString(),
        ]);

        // Volver a generar el mismo mes no duplica: actualiza la cantidad
        $this->certificada($taller, 'CCC333', '2026-09-25 08:00:00');
        $pagina->call('verVehiculos')->call('generar')->assertFileDownloaded('certificadoTaller.pdf');
        $this->assertSame(3, CertificadoTaller::sole()->cantidad);

        $pagina->call('verGenerados')
            ->assertCanSeeTableRecords([$certificado])
            ->callTableAction('descargar', $certificado)
            ->assertFileDownloaded('certificadoTaller.pdf')
            ->callTableAction('eliminar', $certificado)
            ->assertNotified('El certificado se eliminó con éxito');

        $this->assertSame(0, CertificadoTaller::count());
        $this->assertSame(3, Solicitud::whereNotNull('codigo')->count());
        $this->assertSame(1, Actividad::where('evento', 'creado')->where('descripcion', 'like', 'Certificado Taller%')->count());
        $this->assertSame(1, Actividad::where('evento', 'eliminado')->where('descripcion', 'like', 'Certificado Taller%')->count());
    }

    public function test_el_numero_de_certificado_es_un_consecutivo_que_sigue_donde_iba_la_plataforma_anterior(): void
    {
        $this->actingAs($this->usuario('admin'));
        $taller = $this->usuario('cliente');
        \App\Models\SystemSetting::put('consecutivo_certificado', true, ['siguiente' => 161272]);
        $this->certificada($taller, 'OCU111', '2026-10-01 08:00:00', ['codigo' => '161273']); // número ya usado

        $nuevas = collect(['NUE111', 'NUE222'])->map(function (string $placa) use ($taller) {
            $solicitud = $this->solicitud($taller, ['vehicle_identification' => $placa]);
            $solicitud->emitirCertificado();

            return $solicitud->fresh();
        });

        $this->assertSame(['161272', '161274'], $nuevas->pluck('codigo')->all());
        $this->assertSame(161275, Solicitud::siguienteNumeroCertificado());

        // Reenviar tras una devolución conserva el número
        $nuevas[0]->update(['estado' => EstadoSolicitud::Evaluada->value]);
        $nuevas[0]->emitirCertificado();
        $this->assertSame('161272', $nuevas[0]->fresh()->codigo);

        // El PDF del taller cabe en dos páginas: certificado y Anexo I
        $certificado = CertificadoTaller::create(['taller_id' => $taller->id, 'fecha_inicio' => now()->startOfMonth(), 'fecha_fin' => now()->endOfMonth(), 'cantidad' => 2]);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b#', $certificado->pdf()->output()));
    }

    public function test_solo_el_admin_entra(): void
    {
        foreach (['cliente', 'evaluador', 'revisor'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->assertFalse(Pagina::canAccess());
        }
    }
}
