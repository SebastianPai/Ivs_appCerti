<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Exports\Exportables;
use App\Filament\Pages\Exportables as PaginaExportables;
use App\Filament\Resources\CylinderBrands\CylinderBrandResource;
use App\Filament\Resources\CylinderBrands\Pages\ManageCylinderBrands;
use App\Models\Actividad;
use App\Models\Chip;
use App\Models\CylinderBrand;
use App\Models\RegulatorBrand;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VehicleIdentificationType;
use Filament\Actions\CreateAction;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExportablesTest extends TestCase
{
    private const DIA = ['desde' => '2026-10-09', 'hasta' => '2026-10-09'];

    /** Solicitud inspeccionada el 9 de octubre de 2026 y, si se pide, certificada ese mismo día. */
    private function inspeccionada(User $taller, User $evaluador, string $placa, bool $certificada = true): Solicitud
    {
        $solicitud = $this->solicitud($taller, ['vehicle_identification' => $placa]);
        $solicitud->verificaciones()->create([
            'evaluador_id' => $evaluador->id,
            'estado' => 'enviada',
            'verificada_en' => '2026-10-09 07:15:00',
            'id_chip' => Chip::create(['codigo' => '1A000000'.$placa])->id,
        ]);
        $solicitud->update($certificada ? [
            'estado' => EstadoSolicitud::Aprobada->value,
            'codigo' => 'IVS-2026-'.$placa,
            'fecha_aprobacion' => '2026-10-09 16:30:00',
            'revisada_en' => '2026-10-09 16:30:00',
            'vence_el' => '2027-10-09',
        ] : ['estado' => EstadoSolicitud::Evaluada->value]);

        return $solicitud;
    }

    public function test_sicom_saca_una_fila_por_cilindro_con_codigos_y_fechas_del_formato(): void
    {
        $admin = $this->usuario('admin');
        $solicitud = $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), 'ABC123');
        $solicitud->update(['fecha_instalacion' => '2019-03-15']);
        $solicitud->cilindros()->create(['brand_id' => CylinderBrand::first()->id, 'numero_serie' => 'CIL-2', 'capacidad' => 40]);
        CylinderBrand::query()->update(['codigo_sicom' => '21']);
        RegulatorBrand::query()->update(['codigo_sicom' => '35']);

        $filas = Exportables::filas('sicom', self::DIA, $admin);

        $this->assertCount(12, Exportables::ENCABEZADOS['sicom']);
        $this->assertSame(
            ['ABC123', '', '15/03/2019', '1A000000ABC123', '09/10/2026', 'CIL-1', '21', '60', '01/01/2029', '01/01/2020', 'REG-1', '35'],
            $filas[0],
        );
        $this->assertSame(['ABC123', '', '15/03/2019', '1A000000ABC123', '09/10/2026', 'CIL-2', '21', '40', '', '', 'REG-1', '35'], $filas[1]);
        $this->assertCount(2, $filas);
    }

    public function test_sicom_incluye_el_vehiculo_sin_cilindros_y_deduce_la_instalacion(): void
    {
        $admin = $this->usuario('admin');
        $solicitud = $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), 'XYZ987');
        $solicitud->cilindros()->delete();
        $solicitud->regulators()->delete();

        // Sin fecha real: 1 de enero del año modelo (el vehículo de prueba es 2015)
        $this->assertSame(
            ['XYZ987', '', '01/01/2015', '1A000000XYZ987', '09/10/2026', '', '', '', '', '', '', ''],
            Exportables::filas('sicom', self::DIA, $admin)[0],
        );

        // En un servicio inicial la instalación es el servicio mismo
        $solicitud->serviceType->update(['clase' => 'inicial']);
        $this->assertSame('09/10/2026', Exportables::filas('sicom', self::DIA, $admin)[0][2]);
    }

    public function test_identificado_por_chasis_va_en_vin_y_deja_la_placa_vacia(): void
    {
        $solicitud = $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), '9BWZZZ377VT004251');
        $solicitud->update(['vehicle_identification_type_id' => VehicleIdentificationType::firstOrCreate(['nombre' => 'Chasis'])->id]);
        $admin = $this->usuario('admin');

        $this->assertSame(['', '9BWZZZ377VT004251'], array_slice(Exportables::filas('sicom', self::DIA, $admin)[0], 0, 2));
        $this->assertSame(['', '9BWZZZ377VT004251'], array_slice(Exportables::filas('externo', self::DIA, $admin)[0], 2, 2));
    }

    public function test_por_certificado_excluye_el_cambio_de_chip_y_usa_los_codigos_del_tipo(): void
    {
        $admin = $this->usuario('admin');
        $anual = $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), 'ABC123');
        $anual->serviceType->update(['clase' => 'anual', 'codigo_exportado' => '2', 'nombre_exportado' => 'REVISION ANUAL']);
        $anual->vehicle->update(['year' => 1998]);
        $cambio = $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), 'CHP456');
        $cambio->update(['service_type_id' => ServiceType::create(['nombre' => 'CAMBIO DE CHIP', 'clase' => 'cambio_chip', 'activo' => true])->id]);

        $filas = Exportables::filas('externo', self::DIA, $admin);

        $this->assertCount(1, $filas);
        $this->assertSame(['2', 'REVISION ANUAL', 'ABC123'], array_slice($filas[0], 0, 3));
        $this->assertSame('2007-02-01', $filas[0][8], 'un modelo anterior a 2007 se sube a 2007');
        $this->assertSame('900524342', $filas[0][12], 'NIT del certificador por defecto');
        $this->assertCount(2, array_unique(array_column(Exportables::filas('sicom', self::DIA, $admin), 0)), 'por cilindro sí entra el cambio de chip');
    }

    public function test_por_certificado_trae_los_nit_y_solo_lo_certificado(): void
    {
        $taller = $this->usuario('cliente', ['nit' => '900000001-1', 'city' => 'Bogotá']);
        $evaluador = $this->usuario('evaluador');
        $certificada = $this->inspeccionada($taller, $evaluador, 'ABC123');
        $this->inspeccionada($taller, $evaluador, 'SIN000', certificada: false);
        SystemSetting::put('organismo', true, ['nit' => '900000000']);

        // El externo exporta solo los talleres que tiene asignados
        $externo = $this->usuario('externo');
        $this->assertSame([], Exportables::filas('externo', self::DIA, $externo));
        $externo->talleresExportables()->attach($taller);

        $filas = Exportables::filas('externo', self::DIA, $externo);

        $this->assertCount(1, $filas);
        $this->assertSame([
            (string) $certificada->service_type_id, 'Conversión a GNV', 'ABC123', '', '1A000000ABC123', $taller->name, '900000001-1',
            'Bogotá', '2015-02-01', '2026-10-09', '2027-10-09', $evaluador->name, '900000000', 'IVS-2026-ABC123',
        ], $filas[0]);
    }

    public function test_el_del_revisor_incluye_el_dia_final_y_respeta_su_alcance(): void
    {
        $evaluador = $this->usuario('evaluador');
        $ajeno = $this->usuario('evaluador');
        $revisor = $this->usuario('revisor');
        $revisor->evaluadoresSupervisados()->attach($evaluador);
        $this->usuario('revisor')->evaluadoresSupervisados()->attach($ajeno);
        $taller = $this->usuario('cliente', ['city' => 'Medellín']);
        $propia = $this->inspeccionada($taller, $evaluador, 'ABC123');
        $this->inspeccionada($taller, $ajeno, 'AJE456');

        // Fecha inicial = final: el día entra completo (en la plataforma anterior salía vacío)
        $filas = Exportables::filas('revisor', self::DIA, $revisor);

        $this->assertCount(1, $filas);
        $this->assertSame([
            (string) $propia->id, 'ABC123', $taller->name, 'Medellín', $evaluador->name, '2026-10-09 07:15:00',
            '2027-10-09 00:00:00', 'IVS-2026-ABC123', 'Conversión a GNV', 'Aprobada', '2026-10-09 16:30:00',
        ], $filas[0]);
        $this->assertCount(2, Exportables::filas('revisor', self::DIA, $this->usuario('admin')));
        $this->assertSame([], Exportables::filas('revisor', ['desde' => '2026-10-10', 'hasta' => '2026-10-10'], $revisor));
    }

    public function test_ivs_cambia_la_columna_usuario_segun_el_modo_y_filtra_por_usuario(): void
    {
        $admin = $this->usuario('admin');
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $this->inspeccionada($taller, $evaluador, 'ABC123');
        $this->inspeccionada($this->usuario('cliente'), $this->usuario('evaluador'), 'OTR456');

        $porTaller = Exportables::filas('ivs', [...self::DIA, 'modo' => 'taller', 'usuario_id' => $taller->id], $admin);
        $porEvaluador = Exportables::filas('ivs', [...self::DIA, 'modo' => 'inspector'], $admin);

        $this->assertCount(1, $porTaller);
        $this->assertSame('ABC123', $porTaller[0][0]);
        $this->assertSame($taller->name, $porTaller[0][2]);
        $this->assertSame(['2026-10-09 16:30:00', $porTaller[0][4], '2026-10-09', now()->format('Y-m-d'), 'IVS-2026-ABC123'], array_slice($porTaller[0], 3));
        $this->assertCount(2, $porEvaluador);
        $this->assertSame($evaluador->name, $porEvaluador[0][2]);

        // Por fecha de creación las solicitudes de hoy no caen en el 9 de octubre
        $this->assertSame([], Exportables::filas('ivs', [...self::DIA, 'tipo_fecha' => 'creacion'], $admin));
    }

    public function test_operaciones_solo_trae_las_del_evaluador(): void
    {
        $evaluador = $this->usuario('evaluador');
        $taller = $this->usuario('cliente');
        $propia = $this->inspeccionada($taller, $evaluador, 'ABC123', certificada: false);
        $this->inspeccionada($taller, $this->usuario('evaluador'), 'AJE456');

        $filas = Exportables::filas('operaciones', self::DIA, $evaluador);

        $this->assertSame(
            ['id', 'taller', 'servicio', 'placa_chasis', 'numero_certificado', 'fecha_expedicion', 'evaluador', 'estado'],
            Exportables::ENCABEZADOS['operaciones'],
        );
        $this->assertSame([[
            (string) $propia->id, mb_strtoupper($taller->name), 'Conversión a GNV', 'ABC123', '', '2026-10-09 07:15:00',
            mb_strtoupper($evaluador->name), 'En revisión final',
        ]], $filas);
        $this->assertSame([], Exportables::filas('operaciones', [...self::DIA, 'placa' => 'ZZZ'], $evaluador));
    }

    public function test_el_del_taller_trae_solo_sus_solicitudes_con_las_columnas_del_original(): void
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $certificada = $this->inspeccionada($taller, $evaluador, 'ABC123');
        $creada = $this->solicitud($taller, ['vehicle_identification' => 'NUE456']);
        $this->inspeccionada($this->usuario('cliente'), $evaluador, 'OTR789');

        $this->assertSame(
            ['id', 'estado', 'tipo', 'identificacion', 'certificate', 'tipo_servicio', 'nombre_cliente', 'telefono_cliente', 'numbCer', 'fecha_certificacion'],
            Exportables::ENCABEZADOS['cliente'],
        );
        $this->assertStringStartsWith('ExportadoCliente_export_', Exportables::nombreArchivo('cliente'));

        // Por inspección (lo marcado por defecto): la recién creada no tiene inspección y no sale
        $this->assertSame([[
            (string) $certificada->id, 'Aprobada', 'PLACA', 'ABC123', 'Certificado', 'Conversión a GNV', 'Ana Pérez', '3001234567',
            'IVS-2026-ABC123', '2026-10-09 16:30:00',
        ]], Exportables::filas('cliente', self::DIA, $taller));

        // Por creación: las dos, de la más nueva a la más vieja
        $hoy = ['desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'fecha_de' => 'creacion'];
        $filas = Exportables::filas('cliente', $hoy, $taller);
        $this->assertSame([(string) $creada->id, (string) $certificada->id], array_column($filas, 0));
        $this->assertSame(
            [(string) $creada->id, 'Pendiente de evaluación', 'PLACA', 'NUE456', 'No certificado', 'Conversión a GNV', 'Ana Pérez', '3001234567', '', ''],
            $filas[0],
        );
        $this->assertCount(1, Exportables::filas('cliente', [...$hoy, 'certificada' => 'no'], $taller));
        $this->assertCount(3, Exportables::filas('cliente', $hoy, $this->usuario('admin')));
    }

    public function test_cada_rol_solo_puede_con_sus_exportables(): void
    {
        $this->assertSame(['externo'], array_keys(Exportables::tiposPara($this->usuario('externo'))));
        $this->assertSame(['revisor'], array_keys(Exportables::tiposPara($this->usuario('revisor'))));
        $this->assertSame(['operaciones'], array_keys(Exportables::tiposPara($this->usuario('evaluador'))));
        $this->assertSame(['cliente'], array_keys(Exportables::tiposPara($this->usuario('cliente'))));
        $this->assertCount(6, Exportables::tiposPara($this->usuario('admin')));

        $this->expectException(HttpException::class);
        Exportables::filas('sicom', self::DIA, $this->usuario('externo'));
    }

    public function test_las_columnas_desmarcadas_no_salen_y_por_defecto_salen_todas(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin);
        $taller = $this->usuario('cliente');
        $this->inspeccionada($taller, $this->usuario('evaluador'), 'ABC123');
        $ruta = storage_path('app/private/prueba-columnas.xlsx');

        // Se respeta el orden del formato aunque se pidan en otro
        Exportables::escribir('externo', Exportables::filas('externo', self::DIA, $admin), $ruta, ['numero_certificado', 'placa']);

        $reader = new Reader;
        $reader->open($ruta);
        $filas = [];
        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
        }
        $reader->close();
        @unlink($ruta);

        $this->assertSame([['placa', 'numero_certificado'], ['ABC123', 'IVS-2026-ABC123']], $filas);

        Livewire::test(PaginaExportables::class, ['tipo' => 'externo'])
            ->assertFormSet(['tipo' => 'externo'])
            ->fillForm(['tipo' => 'externo'])
            ->assertFormSet(['columnas' => Exportables::ENCABEZADOS['externo']])
            ->assertFormFieldHidden('columnas')
            ->fillForm([...self::DIA, 'personalizar' => true])
            ->assertFormFieldVisible('columnas')
            ->fillForm(['columnas' => []])
            ->call('exportar')
            ->assertHasFormErrors(['columnas'])
            ->fillForm(['columnas' => ['placa', 'taller']])
            ->call('exportar')
            ->assertFileDownloaded();

        $this->assertStringContainsString('Sin las columnas: id_tipo_certificado', Actividad::where('evento', 'exportacion')->sole()->descripcion);
    }

    public function test_el_archivo_tiene_una_hoja_data_con_encabezados_exactos_y_todo_texto(): void
    {
        $taller = $this->usuario('cliente');
        $this->inspeccionada($taller, $this->usuario('evaluador'), 'ABC123');
        $ruta = storage_path('app/private/prueba-exportable.xlsx');

        $total = Exportables::escribir('sicom', Exportables::filas('sicom', self::DIA, $this->usuario('admin')), $ruta);

        $reader = new Reader;
        $reader->open($ruta);
        $hojas = [];
        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }
        $reader->close();
        @unlink($ruta);

        $this->assertSame(1, $total);
        $this->assertSame(['data'], array_keys($hojas));
        $this->assertSame(Exportables::ENCABEZADOS['sicom'], $hojas['data'][0]);
        $this->assertSame('60', $hojas['data'][1][7], 'la capacidad viaja como texto, no como número');
        $this->assertStringStartsWith('ExportadoSicom_export_', Exportables::nombreArchivo('sicom'));
        $this->assertMatchesRegularExpression('/^ExportadoSicom_export_\d{13}\.xlsx$/', Exportables::nombreArchivo('externo'));
    }

    public function test_la_pagina_avisa_sin_registros_y_descarga_dejando_rastro(): void
    {
        $externo = $this->usuario('externo');
        $this->actingAs($externo);

        $this->get(PaginaExportables::getUrl())->assertOk();
        $this->get('/ivs')->assertOk();

        Livewire::test(PaginaExportables::class)
            ->assertFormSet(['tipo' => 'externo'])
            ->fillForm(self::DIA)
            ->call('exportar')
            ->assertNotified('No se encontraron registros')
            ->assertNoFileDownloaded();

        $taller = $this->usuario('cliente');
        $externo->talleresExportables()->attach($taller);
        $this->inspeccionada($taller, $this->usuario('evaluador'), 'ABC123');

        Livewire::test(PaginaExportables::class)
            ->fillForm(self::DIA)
            ->call('exportar')
            ->assertFileDownloaded();

        // «Filtrar» muestra el resultado en pantalla y no descarga ni deja rastro de exportación
        Livewire::test(PaginaExportables::class)
            ->fillForm(self::DIA)
            ->call('filtrar')
            ->assertNoFileDownloaded()
            ->assertSet('vista.total', 1)
            ->assertSee('numero_certificado')
            ->assertSee('ABC123')
            ->call('limpiar')
            ->assertSet('vista', null);

        $rastro = Actividad::where('evento', 'exportacion')->sole();
        $this->assertSame($externo->id, $rastro->user_id);
        $this->assertStringContainsString('09/10/2026 a 09/10/2026, 1 fila(s)', $rastro->descripcion);

        Livewire::test(PaginaExportables::class)
            ->fillForm(['desde' => '2025-01-01', 'hasta' => '2026-10-09'])
            ->call('exportar')
            ->assertNotified('El rango máximo es de un año');
    }

    public function test_el_taller_solo_entra_a_su_exportable_y_no_a_los_catalogos_de_marcas(): void
    {
        $this->actingAs($this->usuario('cliente'));

        $this->get(PaginaExportables::getUrl())->assertOk();
        Livewire::test(PaginaExportables::class)->assertFormSet(['tipo' => 'cliente']);
        $this->get(PaginaExportables::getUrl(['tipo' => 'sicom']))->assertOk();
        Livewire::withQueryParams(['tipo' => 'sicom'])->test(PaginaExportables::class)->assertFormSet(['tipo' => 'cliente']);
        $this->get(CylinderBrandResource::getUrl())->assertForbidden();
    }

    public function test_el_admin_registra_una_marca_con_su_codigo_sicom(): void
    {
        $this->actingAs($this->usuario('admin'));

        Livewire::test(ManageCylinderBrands::class)
            ->callAction(CreateAction::class, ['nombre' => 'FABER', 'codigo_sicom' => '21'])
            ->assertHasNoActionErrors();

        $this->assertSame('21', CylinderBrand::where('nombre', 'FABER')->value('codigo_sicom'));
    }
}
