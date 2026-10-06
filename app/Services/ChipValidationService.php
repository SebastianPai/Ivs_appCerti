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

        if (! $chip) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» no está registrado en el sistema.",
            ]);
        }

        if (! $chip->activo) {
            throw ValidationException::withMessages([
                $campo => "El chip «{$codigo}» está inactivo.",
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

        return $chip;
    }
}
