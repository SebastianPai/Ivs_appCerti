<?php

namespace App\Console\Commands;

use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\Actividad;
use App\Models\Solicitud;
use App\Models\SystemSetting;
use App\Support\Correo;
use Illuminate\Console\Command;

/**
 * Avisa al taller (en la app y por correo) y al propietario (por correo) que el certificado
 * está por vencer. Cada certificado se avisa una sola vez. Se ejecuta a diario (routes/console.php).
 */
class AvisarVencimientos extends Command
{
    protected $signature = 'ivs:avisar-vencimientos';

    protected $description = 'Envía los avisos de certificados próximos a vencer';

    public function handle(): int
    {
        $dias = SystemSetting::vigencia()['dias_aviso'];
        $enviados = 0;

        Solicitud::query()
            ->porRenovar($dias)
            ->whereNull('aviso_vencimiento_at')
            ->whereDate('vence_el', '>=', now()->toDateString()) // los ya vencidos no se avisan tarde
            ->with(['user', 'vehicle'])
            ->chunkById(100, function ($solicitudes) use (&$enviados) {
                foreach ($solicitudes as $solicitud) {
                    $this->avisar($solicitud);
                    $enviados++;
                }
            });

        $this->info("Avisos de vencimiento enviados: {$enviados}");

        return self::SUCCESS;
    }

    private function avisar(Solicitud $solicitud): void
    {
        $placa = $solicitud->placa();
        $fecha = $solicitud->vence_el->format('d/m/Y');
        $asunto = "⏰ Certificado por vencer - Placa: {$placa}";
        $datos = [
            'titulo' => 'Certificado próximo a vencer',
            'cuerpo' => "El certificado de la placa {$placa} vence el {$fecha}. Programe la revisión periódica a tiempo para evitar inconvenientes.",
            'placa' => $placa,
            'detalle' => "Certificado N.º {$solicitud->codigo} · vence el {$fecha}",
        ];

        Correo::enviar($solicitud->user, 'vencimiento', $asunto, 'emails.notificacion', [
            ...$datos,
            'saludo' => $solicitud->user?->name,
            'boton_texto' => 'Iniciar la renovación',
            'boton_url' => SolicitudResource::getUrl('create', ['desde' => $solicitud->id], panel: 'admin'),
        ]);

        Correo::enviar($solicitud->owner_email, 'vencimiento', $asunto, 'emails.notificacion', [
            ...$datos,
            'saludo' => trim($solicitud->owner_nombre.' '.$solicitud->owner_apellido),
            'cuerpo' => $datos['cuerpo'].' Comuníquese con su taller ('.($solicitud->user?->name ?? 'taller').') para agendarla.',
        ]);

        $solicitud->forceFill(['aviso_vencimiento_at' => now()])->save();

        Actividad::registrar('aviso', "Aviso de vencimiento enviado (vence el {$fecha})", $solicitud, $solicitud->id);
    }
}
