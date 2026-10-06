<?php

namespace App\Exports;

use App\Enums\EstadoSolicitud;
use App\Models\Solicitud;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exporta solicitudes a Excel (.xlsx) de forma directa (sin colas).
 * Recibe la consulta ya filtrada por la tabla, así respeta permisos, pestaña, filtros y búsqueda.
 *
 * Hoja 1 "Solicitudes": una fila por solicitud.
 * Hoja 2 "Equipos": una fila por cilindro o regulador.
 */
class SolicitudesExport
{
    private const ENCABEZADOS = [
        'N.º', 'Certificado', 'Estado', 'Fecha solicitud', 'Fecha aprobación', 'Vencimiento',
        'Placa / chasis', 'Tipo identificación', 'Marca', 'Línea', 'Año', 'Clase', 'Combustible original', 'Motor',
        'Servicio', 'Sistema de combustión', 'Aplicación', 'Tecnología',
        'Taller', 'Propietario', 'Tipo doc.', 'Documento', 'Teléfono', 'Correo', 'Departamento', 'Ciudad', 'Dirección',
        'Chip', 'Evaluador', 'Fecha evaluación', 'Revisor', 'N.º cilindros', 'N.º reguladores',
    ];

    public static function descargar(Builder $consulta, string $nombre = 'solicitudes')
    {
        $archivo = $nombre.'-'.now()->format('Y-m-d-His').'.xlsx';
        $ruta = storage_path('app/private/'.$archivo);

        self::escribir($consulta, $ruta);

        return response()->download($ruta, $archivo)->deleteFileAfterSend();
    }

    public static function escribir(Builder $consulta, string $ruta): int
    {
        $titulo = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('B91C1C');

        $writer = new Writer;
        $writer->openToFile($ruta);

        $hoja = $writer->getCurrentSheet();
        $hoja->setName('Solicitudes');
        $hoja->setColumnWidthForRange(18, 1, count(self::ENCABEZADOS));
        $writer->addRow(Row::fromValues(self::ENCABEZADOS, $titulo));

        $equipos = [];
        $total = 0;

        $consulta->with([
            'user', 'serviceType', 'combustionSystem', 'applicationType', 'technology', 'vehicleIdentificationType',
            'vehicle.brand', 'vehicle.model', 'vehicle.type', 'verificacion.evaluador', 'verificacion.chip', 'revisor',
            'cilindros.brand', 'regulators.brand',
        ])->lazy(200)->each(function (Solicitud $s) use ($writer, &$equipos, &$total) {
            $writer->addRow(Row::fromValues(self::fila($s)));
            $total++;

            foreach ($s->cilindros as $c) {
                $equipos[] = [$s->id, $s->vehicle_identification, 'Cilindro', $c->brand?->nombre, $c->numero_serie, $c->capacidad, $c->fecha_fabricacion, $c->fecha_prueba];
            }
            foreach ($s->regulators as $r) {
                $equipos[] = [$s->id, $s->vehicle_identification, 'Regulador', $r->brand?->nombre, $r->numero_serie, null, null, null];
            }
        });

        $hoja = $writer->addNewSheetAndMakeItCurrent();
        $hoja->setName('Equipos');
        $hoja->setColumnWidthForRange(18, 1, 8);
        $writer->addRow(Row::fromValues(['N.º solicitud', 'Placa', 'Tipo', 'Marca', 'Serie', 'Capacidad (L)', 'Fabricación', 'Prueba hidrostática'], $titulo));
        foreach ($equipos as $fila) {
            $writer->addRow(Row::fromValues($fila));
        }

        $writer->close();

        return $total;
    }

    private static function fila(Solicitud $s): array
    {
        $v = $s->vehicle;
        $verificacion = $s->verificacion;

        return [
            $s->id,
            $s->codigo,
            EstadoSolicitud::labelDe($s->estado),
            $s->created_at?->format('Y-m-d H:i'),
            $s->fecha_aprobacion?->format('Y-m-d H:i'),
            $s->fecha_aprobacion?->copy()->addYear()->format('Y-m-d'),
            $s->vehicle_identification,
            $s->vehicleIdentificationType?->nombre,
            $v?->brand?->nombre,
            $v?->model?->nombre,
            $v?->year,
            $v?->type?->nombre,
            $v?->fuel_base,
            $v?->motor,
            $s->serviceType?->nombre,
            $s->combustionSystem?->nombre,
            $s->applicationType?->nombre,
            $s->technology?->nombre,
            $s->user?->name,
            trim($s->owner_nombre.' '.$s->owner_apellido),
            $s->owner_document_type,
            $s->owner_document,
            $s->owner_telefono,
            $s->owner_email,
            $s->owner_departamento,
            $s->owner_ciudad,
            $s->owner_direccion,
            $verificacion?->chip?->codigo,
            $verificacion?->evaluador?->name,
            $verificacion?->verificada_en?->format('Y-m-d H:i'),
            $s->revisor?->name,
            $s->cilindros->count(),
            $s->regulators->count(),
        ];
    }
}
