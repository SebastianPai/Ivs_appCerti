<?php

namespace App\Jobs;

use App\Models\Solicitud;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía un correo en segundo plano. Si el SMTP falla, se reintenta (1 min y 5 min después)
 * y al final queda registrado en storage/logs/laravel.log y en la tabla failed_jobs.
 */
class EnviarCorreo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $para,
        public string $asunto,
        public string $vista,
        public array $datos,
        /** Id de la solicitud cuyo certificado va adjunto (se arma al enviar, no viaja en la cola). */
        public ?int $certificadoDe = null,
    ) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $solicitud = $this->certificadoDe ? Solicitud::find($this->certificadoDe) : null;
        $pdf = $solicitud?->tieneCertificado() ? $solicitud->pdfCertificado()->output() : null;

        Mail::send($this->vista, $this->datos, function ($message) use ($pdf) {
            $message->to($this->para)->subject($this->asunto);

            if ($pdf) {
                $message->attachData($pdf, 'certificado.pdf', ['mime' => 'application/pdf']);
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("No se pudo enviar el correo «{$this->asunto}» a {$this->para}: {$e->getMessage()}");
    }
}
