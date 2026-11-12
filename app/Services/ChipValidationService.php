<?php

namespace App\Services;

use App\Models\Chip;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

class ChipValidationService
{
    /**
     * Normaliza lo que entrega un lector (NFC, lector USB tipo teclado o digitación):
     * quita espacios/saltos de línea y pasa a mayúsculas.
     */
    public static function normalizar(?string $codigo): ?string
    {
        $codigo = strtoupper(trim((string) $codigo));

        return $codigo === '' ? null : $codigo;
    }

    /**
     * Valida el código leído y devuelve el chip (o null si no es obligatorio y no se leyó).
     *
     * @throws ValidationException
     */
    public static function validar(?string $codigo, Solicitud $solicitud, string $campo = 'data.chip_codigo'): ?Chip
    {
        $codigo = self::normalizar($codigo);
        $obligatorio = SystemSetting::enabled('chip_required');

        if (! $codigo) {
            if ($obligatorio) {
                throw ValidationException::withMessages([
                    $campo => 'La lectura del chip es obligatoria para continuar.',
                ]);
            }

            return null;
        }

        $chip = Chip::whereRaw('UPPER(codigo) = ?', [$codigo])->first();
        $chipNuevo = (bool) $solicitud->serviceType?->chip_nuevo;
        // «Permitir consulta a sicom inspector y taller» (Configuración General). Apagado, como está hoy
        // en la plataforma anterior, el chip no se contrasta con SICOM.
        $consultaSicom = SystemSetting::enabled('sicom_consulta_evaluacion');
        $sicom = $consultaSicom ? self::enSicom($codigo) : false;

        // Como en la plataforma anterior: un servicio con chip nuevo no acepta uno que SICOM ya conoce
        // y una revisión solo sigue con el chip que el vehículo tiene registrado en SICOM.
        if ($chipNuevo && $sicom) {
            throw ValidationException::withMessages([
                $campo => 'No es correcto asignar el mismo chip a una solicitud que requiere un cambio de chip o la asignación de uno nuevo.',
            ]);
        }

        if (! $chipNuevo && $sicom && ! self::esDelVehiculo($sicom, $solicitud)) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» está registrado en SICOM para el vehículo ".self::vehiculoEnSicom($sicom).'.',
            ]);
        }

        if (! $chip && ! $chipNuevo && ! $consultaSicom) {
            // Sin consulta a SICOM se acepta el chip que trae el vehículo, tal como lo leyó el evaluador
            $chip = Chip::create(['codigo' => $codigo, 'activo' => false, 'descripcion' => 'Chip del vehículo, sin consulta a SICOM']);
        }

        if (! $chip && ! $chipNuevo) {
            if ($sicom === null) {
                throw ValidationException::withMessages([
                    $campo => 'El chip que está consultando no existe en el SICOM. No es posible continuar.',
                ]);
            }

            if ($sicom === false) {
                throw ValidationException::withMessages([
                    $campo => "El chip «{$codigo}» no está registrado en el sistema y no fue posible verificarlo en SICOM. Intente de nuevo.",
                ]);
            }

            // El chip de una revisión ya viene instalado: no sale del inventario, lo respalda SICOM
            $chip = Chip::create([
                'codigo' => $codigo,
                'activo' => false,
                'descripcion' => 'Chip del vehículo, verificado en SICOM',
            ]);
        }

        if (! $chip) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» no está registrado en el sistema.",
            ]);
        }


        // Un chip identifica a un solo vehículo: no puede usarse en solicitudes de otra placa.
        $usadoEnOtroVehiculo = $chip->verificaciones()
            ->where('solicitud_id', '!=', $solicitud->id)
            ->whereHas('solicitud', fn ($q) => $q->where('vehicle_identification', '!=', $solicitud->vehicle_identification))
            ->with('solicitud:id,vehicle_identification')
            ->first();

        if ($usadoEnOtroVehiculo) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» ya está asociado al vehículo {$usadoEnOtroVehiculo->solicitud->vehicle_identification}.",
            ]);
        }

        // Inicial, cambio de chip y garantía instalan un chip nuevo: no sirve uno que ya se usó
        if ($solicitud->serviceType?->chip_nuevo && $chip->verificaciones()->where('solicitud_id', '!=', $solicitud->id)->exists()) {
            throw ValidationException::withMessages([
                $campo => 'No es correcto asignar el mismo chip a una solicitud que requiere un cambio de chip o la asignación de uno nuevo.',
            ]);
        }

        // Inactivo = ya se gastó en otra solicitud o el admin lo retiró. El de esta misma solicitud
        // sigue valiendo (reenvío tras una devolución) y una revisión usa el chip que ya trae el vehículo.
        // Con «Permitir chips inactivos» (Configuración General) se continúa con chips activos e inactivos;
        // las reglas de arriba (otro vehículo, chip ya usado en un servicio de chip nuevo) siguen aplicando.
        if ($chipNuevo && ! $chip->activo && ! self::permiteInactivos() && ! $chip->verificaciones()->where('solicitud_id', $solicitud->id)->exists()) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» está inactivo.",
            ]);
        }

        return $chip;
    }

    /** «Permitir chips inactivos» en Configuración General (apagado por defecto, como la plataforma anterior). */
    public static function permiteInactivos(): bool
    {
        return SystemSetting::enabled('chip_permitir_inactivo');
    }

    /**
     * Estado de un chip para mostrarlo en el lector apenas se lee (no valida la solicitud: eso lo hace validar()).
     *
     * @return array{codigo: string, registrado: bool, activo: bool|null, usos: int, vehiculo: string|null, permite_inactivos: bool}
     */
    public static function estado(string $codigo): array
    {
        $codigo = (string) self::normalizar($codigo);
        $chip = Chip::whereRaw('UPPER(codigo) = ?', [$codigo])->first();
        $ultima = $chip?->verificaciones()->with('solicitud:id,vehicle_identification')->latest('id')->first();

        return [
            'codigo' => $codigo,
            'registrado' => $chip !== null,
            'activo' => $chip?->activo,
            'usos' => $chip ? $chip->verificaciones()->count() : 0,
            'vehiculo' => $ultima?->solicitud?->vehicle_identification,
            'permite_inactivos' => self::permiteInactivos(),
        ];
    }

    /** Registro del chip en SICOM, null si no existe y false si no se pudo consultar. */
    private static function enSicom(string $codigo): array|false|null
    {
        try {
            return SicomAbierto::porChip($codigo);
        } catch (\Throwable) {
            return false;
        }
    }

    /** SICOM identifica el vehículo por placa o por VIN; lo que no tiene lo marca «SIN DEFINIR». */
    private static function esDelVehiculo(array $sicom, Solicitud $solicitud): bool
    {
        $registrados = array_filter(
            [strtoupper(trim($sicom['placa'] ?? '')), strtoupper(trim($sicom['vin'] ?? ''))],
            fn (string $valor) => $valor !== '' && $valor !== 'SIN DEFINIR',
        );

        return $registrados === [] || in_array(strtoupper((string) $solicitud->vehicle_identification), $registrados, true);
    }

    private static function vehiculoEnSicom(array $sicom): string
    {
        $placa = strtoupper(trim($sicom['placa'] ?? ''));

        return $placa !== '' && $placa !== 'SIN DEFINIR' ? $placa : strtoupper(trim($sicom['vin'] ?? ''));
    }
}
