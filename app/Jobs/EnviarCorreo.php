<?php

namespace App\Jobs;

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
    ) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        Mail::send($this->vista, $this->datos, fn ($message) => $message->to($this->para)->subject($this->asunto));
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("No se pudo enviar el correo «{$this->asunto}» a {$this->para}: {$e->getMessage()}");
    }
}
