<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Exports\SolicitudesExport;
use App\Filament\Resources\Revisor\Pages\ListSolicitudRevisors;
use App\Filament\Resources\Solicituds\Pages\ListSolicituds;
use App\Filament\Resources\Solicituds\SolicitudResource;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ExportacionTest extends TestCase
{
    /** @return array<string, array<int, array<int, mixed>>> filas por hoja */
    private function leer(string $ruta): array
    {
        $reader = new Reader;
        $reader->open($ruta);
        $hojas = [];
        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }
        $reader->close();

        return $hojas;
    }

    public function test_el_excel_tiene_solicitudes_y_equipos_solo_del_taller(): void
    {
        $taller = $this->usuario('cliente');
        $propia = $this->solicitud($taller, ['vehicle_identification' => 'PRO123']);
        $this->solicitud($this->usuario('cliente'), ['vehicle_identification' => 'AJE123']);
        $this->actingAs($taller);

        $ruta = storage_path('app/private/prueba-export.xlsx');
        $total = SolicitudesExport::escribir(SolicitudResource::getEloquentQuery(), $ruta);
        $hojas = $this->leer($ruta);
        @unlink($ruta);

        $this->assertSame(1, $total);
        $this->assertSame(['Solicitudes', 'Equipos'], array_keys($hojas));
        $this->assertSame('Placa / chasis', $hojas['Solicitudes'][0][6]);
        $this->assertSame('PRO123', $hojas['Solicitudes'][1][6]);
        $this->assertSame('Ana Pérez', $hojas['Solicitudes'][1][19]);
        $this->assertCount(3, $hojas['Equipos'], 'encabezado + 1 cilindro + 1 regulador');
        $this->assertSame($propia->id, (int) $hojas['Equipos'][1][0]);
    }

    public function test_el_boton_descarga_respetando_la_pestana_activa(): void
    {
        $taller = $this->usuario('cliente');
        $this->solicitud($taller);
        $this->actingAs($taller);

        // Sin aprobadas: avisa en vez de descargar un archivo vacío
        Livewire::test(ListSolicituds::class)
            ->set('activeTab', 'aprobadas')
            ->callTableAction('exportarExcel')
            ->assertNotified('No hay solicitudes para exportar con estos filtros');

        Livewire::test(ListSolicituds::class)
            ->set('activeTab', 'en_proceso')
            ->callTableAction('exportarExcel')
            ->assertFileDownloaded();
    }

    public function test_el_revisor_exporta_su_auditoria(): void
    {
        $revisor = $this->usuario('revisor');
        $evaluador = $this->usuario('evaluador');
        $solicitud = $this->solicitud($this->usuario('cliente'), ['estado' => EstadoSolicitud::Evaluada->value]);
        $solicitud->verificaciones()->create(['evaluador_id' => $evaluador->id, 'estado' => 'enviada']);

        $this->actingAs($revisor);

        Livewire::test(ListSolicitudRevisors::class)
            ->callTableAction('exportarExcel')
            ->assertFileDownloaded();
    }
}
