<?php

namespace Database\Seeders;

use App\Enums\EstadoSolicitud;
use App\Models\ApplicationType;
use App\Models\CertificadoTaller;
use App\Models\Chip;
use App\Models\CombustionSystem;
use App\Models\CylinderBrand;
use App\Models\RegulatorBrand;
use App\Models\ServiceType;
use App\Models\Solicitud;
use App\Models\Technology;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleIdentificationType;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Solicitudes de prueba en todos los estados, para recorrer la app con datos (revisor, exportables,
 * Certificado Taller). No va en DatabaseSeeder: php artisan db:seed --class=DatosDePruebaSeeder
 */
class DatosDePruebaSeeder extends Seeder
{
    /** Marca de los datos de prueba: con ella se sabe si ya se sembraron. */
    private const CORREO = 'prueba@ivs.test';

    private User $evaluador;

    private User $revisor;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DatosDePruebaSeeder omitido en producción.');

            return;
        }

        if (Solicitud::where('owner_email', self::CORREO)->exists()) {
            $this->command?->info('Los datos de prueba ya existen.');

            return;
        }

        $admin = User::role('admin')->firstOrFail();
        $this->evaluador = User::role('evaluador')->firstOrFail();
        $this->revisor = User::role('revisor')->firstOrFail();
        $cliente = User::role('cliente')->firstOrFail();
        $norte = User::firstOrCreate(
            ['email' => 'taller.norte@cliente.com'],
            ['name' => 'Taller Norte', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'city' => 'Medellín'],
        );
        $norte->hasRole('cliente') || $norte->assignRole('cliente');

        $this->evaluador->clientesAsignados()->syncWithoutDetaching([$cliente->id, $norte->id]);
        $this->evaluador->revisores()->syncWithoutDetaching([$this->revisor->id]);

        // Septiembre de 2026, taller «Cliente»: 8 con número (5 revisadas, 2 sin revisar, 1 devuelta) y 1 cambio de chip
        foreach (['PRA101' => 2, 'PRA102' => 4, 'PRA103' => 9, 'PRA104' => 15, 'PRA105' => 22] as $placa => $dia) {
            $this->certificada($cliente, $placa, "2026-09-{$dia} 09:30:00", revisada: true);
        }
        $this->certificada($cliente, 'PRA106', '2026-09-28 11:00:00');
        $this->certificada($cliente, 'PRA107', '2026-09-30 16:45:00', tipo: 'REVISION ANUAL');
        $this->certificada($cliente, 'PRA108', '2026-09-18 10:10:00')->update([
            'estado' => EstadoSolicitud::CorreccionTecnica->value,
            'observaciones_revisor' => 'La foto del regulador no deja leer el serial.',
            'revisor_id' => $this->revisor->id,
        ]);
        $this->certificada($cliente, 'PRA109', '2026-09-12 08:20:00', tipo: 'CAMBIO DE CHIP');

        // Septiembre, segundo taller
        foreach (['PRN201' => 3, 'PRN202' => 11, 'PRN203' => 19, 'PRN204' => 26] as $placa => $dia) {
            $this->certificada($norte, $placa, "2026-09-{$dia} 14:00:00", revisada: $dia < 15);
        }

        // Octubre: certificadas sin revisar y solicitudes que siguen en trámite
        foreach (['PRA110' => 2, 'PRA111' => 6, 'PRA112' => 9] as $placa => $dia) {
            $this->certificada($cliente, $placa, "2026-10-0{$dia} 10:00:00");
        }
        $this->solicitud($cliente, 'PRA113', '2026-10-10 08:00:00');
        $this->solicitud($norte, 'PRN205', '2026-10-11 09:00:00');
        $this->solicitud($cliente, 'PRA114', '2026-10-08 08:00:00')->update([
            'estado' => EstadoSolicitud::DevueltaTaller->value,
            'observacion_devolucion' => 'La tarjeta de propiedad está ilegible.',
        ]);
        $this->solicitud($cliente, 'PRA115', '2026-10-07 08:00:00')->cancelar();

        // Certificado Taller de septiembre ya generado para «Cliente»
        $certificado = CertificadoTaller::create([
            'taller_id' => $cliente->id,
            'generador_id' => $admin->id,
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'cantidad' => CertificadoTaller::vehiculos($cliente->id, '2026-09-01', '2026-09-30')->count(),
        ]);
        $certificado->forceFill(['created_at' => '2026-10-05 09:00:00'])->save();

        $this->command?->info('Datos de prueba creados: '.Solicitud::where('owner_email', self::CORREO)->count().' solicitudes.');
    }

    /** Solicitud recién creada por el taller (pendiente de evaluación). */
    private function solicitud(User $taller, string $placa, string $creada, string $tipo = 'INICIAL'): Solicitud
    {
        $marca = VehicleBrand::firstOrFail();
        $vehiculo = Vehicle::create([
            'brand_id' => $marca->id,
            'model_id' => VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'nombre' => 'PRUEBA'], ['year_start' => 2010])->id,
            'vehicle_type_id' => VehicleType::firstOrFail()->id,
            'year' => 2018,
            'placa' => $placa,
            'fuel_base' => 'Gasolina',
        ]);

        $solicitud = Solicitud::create([
            'user_id' => $taller->id,
            'vehicle_id' => $vehiculo->id,
            'vehicle_identification_type_id' => VehicleIdentificationType::firstOrFail()->id,
            'vehicle_identification' => $placa,
            'service_type_id' => ServiceType::where('nombre', $tipo)->firstOrFail()->id,
            'combustion_system_id' => CombustionSystem::firstOrFail()->id,
            'application_type_id' => ApplicationType::firstOrFail()->id,
            'technology_id' => Technology::firstOrFail()->id,
            'owner_document_type' => 'CC',
            'owner_document' => '1020304050',
            'owner_nombre' => 'Propietario',
            'owner_apellido' => 'De Prueba',
            'owner_telefono' => '3001234567',
            'owner_email' => self::CORREO,
            'owner_departamento' => 'Antioquia',
            'owner_ciudad' => 'Medellín',
            'owner_direccion' => 'Calle 1 # 2-3',
        ]);
        $solicitud->forceFill(['created_at' => $creada])->saveQuietly();

        $solicitud->regulators()->create(['brand_id' => RegulatorBrand::firstOrFail()->id, 'numero_serie' => 'REG-'.$placa]);
        $solicitud->cilindros()->create([
            'brand_id' => CylinderBrand::firstOrFail()->id,
            'numero_serie' => 'CIL-'.$placa,
            'capacidad' => 60,
            'fecha_fabricacion' => '2020-01-01',
            'fecha_prueba' => '2024-01-01',
        ]);

        return $solicitud;
    }

    /**
     * Solicitud inspeccionada y cerrada en la fecha dada, como queda al enviar la lista con «Cumple»:
     * con número y vigencia, salvo el cambio de chip. No pasa por emitirCertificado() para no enviar correos.
     */
    private function certificada(User $taller, string $placa, string $expedicion, bool $revisada = false, string $tipo = 'INICIAL'): Solicitud
    {
        $fecha = Carbon::parse($expedicion);
        $solicitud = $this->solicitud($taller, $placa, $fecha->copy()->subDay()->toDateTimeString(), $tipo);

        $solicitud->verificaciones()->create([
            'evaluador_id' => $this->evaluador->id,
            'estado' => 'aprobada',
            'verificada_en' => $fecha,
            'id_chip' => Chip::create(['codigo' => '1A00'.$placa, 'activo' => false, 'descripcion' => 'Chip de prueba'])->id,
        ]);

        $solicitud->update([
            'estado' => EstadoSolicitud::Aprobada->value,
            'fecha_aprobacion' => $fecha,
            ...($solicitud->serviceType->esCambioDeChip() ? [] : [
                'codigo' => Solicitud::generarCodigo($solicitud),
                'vence_el' => Solicitud::calcularVencimiento($fecha)->toDateString(),
            ]),
            ...($revisada ? [
                'revisor_id' => $this->revisor->id,
                'revisada_en' => $fecha->copy()->addDays(2),
                'observaciones_revisor' => 'Revisada sin novedad.',
            ] : []),
        ]);

        return $solicitud;
    }
}
